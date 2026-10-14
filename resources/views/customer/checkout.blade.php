<x-public-layout title="Checkout">
    <script>
        function checkout(cfg) {
            return {
                method: cfg.method,
                subtotal: cfg.subtotal,
                items: cfg.items,
                courier: cfg.courier,
                service: cfg.service,
                destinationId: cfg.destinationId,
                destinationLabel: cfg.destinationLabel,
                results: [],
                options: [],
                searching: false,
                loading: false,
                error: '',
                timer: null,
                requestId: 0,

                get selected() {
                    return this.options.find(o => o.service === this.service) || null;
                },
                get shipping() {
                    return this.method === 'delivery' && this.selected ? Number(this.selected.cost) : 0;
                },
                get total() {
                    return this.subtotal + this.shipping;
                },
                get canSubmit() {
                    return this.method === 'pickup' || (!!this.destinationId && !!this.selected);
                },
                get groups() {
                    const labels = { regular: 'Regular', express: 'Express', instant: 'Instant' };
                    return Object.keys(labels)
                        .map(key => ({ key, label: labels[key], items: this.options.filter(o => this.kind(o) === key) }))
                        .filter(g => g.items.length);
                },

                init() {
                    this.$watch('method', () => this.loadCosts());
                    this.$watch('courier', () => { this.service = ''; this.loadCosts(); });

                    if (this.method === 'delivery' && this.destinationId) {
                        this.loadCosts();
                    }
                },

                kind(option) {
                    const text = ((option.service || '') + ' ' + (option.description || '')).toLowerCase();

                    if (/instant|same.?day|sameday|sds|gojek|grab/.test(text)) return 'instant';
                    if (/express|kilat|next.?day|boost|\b(yes|ons|nds)\b/.test(text)) return 'express';

                    return 'regular';
                },

                async request(url, options = {}) {
                    const res = await fetch(url, {
                        ...options,
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': cfg.csrf,
                        },
                    });
                    const body = await res.json().catch(() => ({}));

                    if (!res.ok) {
                        throw new Error(body.message || 'Request failed. Please try again.');
                    }

                    return body.data || [];
                },

                onDestinationInput() {
                    this.destinationId = null;
                    this.options = [];
                    this.service = '';
                    clearTimeout(this.timer);

                    if (this.destinationLabel.trim().length < 3) {
                        this.results = [];
                        return;
                    }

                    this.timer = setTimeout(() => this.searchDestinations(), 400);
                },

                async searchDestinations() {
                    this.searching = true;
                    this.error = '';

                    try {
                        this.results = await this.request(
                            cfg.destinationsUrl + '?q=' + encodeURIComponent(this.destinationLabel.trim())
                        );
                    } catch (e) {
                        this.results = [];
                        this.error = e.message;
                    } finally {
                        this.searching = false;
                    }
                },

                pickDestination(place) {
                    this.destinationId = place.id;
                    this.destinationLabel = place.label
                        || [place.subdistrict_name, place.district_name, place.city_name, place.province_name].filter(Boolean).join(', ');
                    this.results = [];
                    this.service = '';
                    this.loadCosts();
                },

                async loadCosts() {
                    if (this.method !== 'delivery' || !this.destinationId) {
                        this.options = [];
                        return;
                    }

                    const id = ++this.requestId;
                    this.loading = true;
                    this.error = '';

                    try {
                        const data = await this.request(cfg.costUrl, {
                            method: 'POST',
                            body: JSON.stringify({
                                destination_id: this.destinationId,
                                courier: this.courier,
                                items: this.items,
                            }),
                        });

                        if (id !== this.requestId) return;

                        this.options = data;

                        if (!this.options.some(o => o.service === this.service)) {
                            this.service = '';
                        }
                    } catch (e) {
                        if (id !== this.requestId) return;
                        this.options = [];
                        this.service = '';
                        this.error = e.message;
                    } finally {
                        if (id === this.requestId) this.loading = false;
                    }
                },

                rupiah(n) {
                    return new Intl.NumberFormat('id-ID', {
                        style: 'currency',
                        currency: 'IDR',
                        maximumFractionDigits: 0
                    }).format(n);
                },
            };
        }
    </script>

    @php
        $user = auth()->user();
        $config = [
            'method' => old('fulfillment', 'pickup'),
            'subtotal' => (int) $subtotal,
            'items' => $items->map(fn ($i) => ['product_id' => $i->product->id, 'qty' => $i->qty])->values(),
            'courier' => old('courier', 'jne'),
            'service' => old('service', ''),
            'destinationId' => old('destination_id', $user->destination_id),
            'destinationLabel' => old('destination', $user->destination_label ?? ''),
            'csrf' => csrf_token(),
            'destinationsUrl' => url('/api/shipping/destinations'),
            'costUrl' => url('/api/shipping/cost'),
        ];
    @endphp

    <div class="mx-auto max-w-7xl px-4 py-8 md:py-12" x-data="checkout({{ Js::from($config) }})">
        <x-page-header eyebrow="Almost there" title="Checkout" />

        <form method="POST" action="{{ route('checkout.store') }}" class="mt-10 grid gap-8 lg:grid-cols-3">
            @csrf

            <div class="space-y-6 lg:col-span-2">
                {{-- Contact --}}
                <div class="rounded-[2rem] bg-white p-6 shadow-sm ring-1 ring-blush/60 md:p-8">
                    <div class="mb-5 flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-blush font-display text-lg font-semibold text-moss">1</span>
                        <h2 class="font-display text-3xl font-semibold">Contact</h2>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Full name</label>
                            <input type="text" name="name" required class="input"
                                value="{{ old('name', $user->name) }}">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Phone</label>
                            <input type="text" name="phone" required class="input"
                                value="{{ old('phone', $user->phone) }}">
                        </div>
                        <div class="md:col-span-2">
                            <label class="mb-1 block text-sm font-medium">Email (optional)</label>
                            <input type="email" name="email" class="input"
                                value="{{ old('email', $user->email) }}">
                        </div>
                    </div>
                </div>

                {{-- Fulfillment --}}
                <div class="rounded-[2rem] bg-white p-6 shadow-sm ring-1 ring-blush/60 md:p-8">
                    <div class="mb-5 flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-blush font-display text-lg font-semibold text-moss">2</span>
                        <h2 class="font-display text-3xl font-semibold">How do you want to receive it?</h2>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-blush p-4 transition hover:bg-blush/20"
                            :class="method === 'pickup' ? 'border-moss bg-sage/10 ring-2 ring-sage/40' : ''">
                            <input type="radio" name="fulfillment" value="pickup" x-model="method" class="mt-1 accent-moss">
                            <span>
                                <span class="block font-semibold">Pick up in store</span>
                                <span class="text-xs text-ink/60">Free. We will tell you when it is ready.</span>
                            </span>
                        </label>

                        <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-blush p-4 transition hover:bg-blush/20"
                            :class="method === 'delivery' ? 'border-moss bg-sage/10 ring-2 ring-sage/40' : ''">
                            <input type="radio" name="fulfillment" value="delivery" x-model="method" class="mt-1 accent-moss">
                            <span>
                                <span class="block font-semibold">Delivery</span>
                                <span class="text-xs text-ink/60">Shipped by courier to your address.</span>
                            </span>
                        </label>
                    </div>

                    @error('fulfillment')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror

                    <div x-show="method === 'delivery'" x-cloak class="mt-6 grid gap-4 md:grid-cols-2">
                        {{-- Destination search --}}
                        <div class="relative md:col-span-2" @click.outside="results = []">
                            <label class="mb-1 block text-sm font-medium">City / district</label>
                            <input type="text" name="destination" class="input" autocomplete="off"
                                placeholder="Type at least 3 letters, then pick a place"
                                x-model="destinationLabel" @input="onDestinationInput()">
                            <input type="hidden" name="destination_id" :value="destinationId">

                            <p x-show="searching" x-cloak class="mt-1 text-xs text-ink/50">Searching...</p>

                            <ul x-show="results.length" x-cloak
                                class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-2xl border border-blush bg-white shadow-xl">
                                <template x-for="place in results" :key="place.id">
                                    <li>
                                        <button type="button" @click="pickDestination(place)"
                                            class="block w-full px-4 py-2.5 text-left text-sm hover:bg-blush/40"
                                            x-text="place.label || [place.subdistrict_name, place.district_name, place.city_name, place.province_name].filter(Boolean).join(', ')"></button>
                                    </li>
                                </template>
                            </ul>

                            @error('destination_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium">Courier</label>
                            <select name="courier" class="input" x-model="courier">
                                @foreach (['jne' => 'JNE', 'tiki' => 'TIKI', 'pos' => 'POS Indonesia', 'sicepat' => 'SiCepat', 'jnt' => 'J&T'] as $code => $label)
                                <option value="{{ $code }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="md:col-span-2">
                            <label class="mb-1 block text-sm font-medium">Shipping service</label>

                            <p x-show="!destinationId" class="text-sm text-ink/50">Pick a destination to see shipping options.</p>
                            <p x-show="loading" x-cloak class="text-sm text-ink/50">Loading shipping options...</p>
                            <p x-show="error" x-cloak x-text="error" class="rounded-xl bg-blush/50 p-3 text-sm"></p>
                            <p x-show="destinationId && !loading && !error && !options.length" x-cloak class="text-sm text-ink/50">
                                No service available for this courier. Try another courier.
                            </p>

                            <div class="space-y-4">
                                <template x-for="group in groups" :key="group.key">
                                    <div>
                                        <p class="mb-2 text-xs font-semibold uppercase tracking-[0.2em] text-moss" x-text="group.label"></p>
                                        <div class="grid gap-2 md:grid-cols-2">
                                            <template x-for="option in group.items" :key="option.service">
                                                <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-blush p-3 transition hover:bg-blush/20"
                                                    :class="service === option.service ? 'border-moss bg-sage/10 ring-2 ring-sage/40' : ''">
                                                    <input type="radio" name="service" :value="option.service" x-model="service" class="mt-1 accent-moss">
                                                    <span class="text-sm">
                                                        <span class="block font-semibold" x-text="option.service"></span>
                                                        <span class="block text-xs text-ink/60" x-text="option.description || ''"></span>
                                                        <span class="block text-xs text-ink/60" x-text="rupiah(option.cost) + (option.etd ? ' (' + option.etd + ')' : '')"></span>
                                                    </span>
                                                </label>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            @error('service')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label class="mb-1 block text-sm font-medium">Full address</label>
                            <textarea name="address" rows="3" class="input">{{ old('address', $user->address) }}</textarea>
                            @error('address')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Special note --}}
                <div class="rounded-[2rem] bg-white p-6 shadow-sm ring-1 ring-blush/60 md:p-8">
                    <div class="mb-5 flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-blush font-display text-lg font-semibold text-moss">3</span>
                        <h2 class="font-display text-3xl font-semibold">Special note <span class="text-base font-normal text-ink/50">(optional)</span></h2>
                    </div>
                    <textarea name="notes" rows="3" maxlength="500" class="input"
                        placeholder="Card message, preferred time, or anything we should know.">{{ old('notes') }}</textarea>
                    @error('notes')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Summary --}}
            <div>
                <div class="rounded-[2rem] bg-white p-6 shadow-sm ring-1 ring-blush/60 lg:sticky lg:top-24">
                    <h2 class="font-display text-3xl font-semibold">Order summary</h2>

                    <ul class="mt-5 space-y-3 text-sm">
                        @foreach ($items as $item)
                        <li class="flex items-center gap-3">
                            <x-product-image :product="$item->product" class="h-14 w-14 shrink-0 rounded-xl" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium">{{ $item->product->product_name }}</span>
                                <span class="text-xs text-ink/60">Qty {{ $item->qty }}</span>
                            </span>
                            <span class="shrink-0 font-semibold">{{ rupiah($item->subtotal) }}</span>
                        </li>
                        @endforeach
                    </ul>

                    <div class="mt-5 space-y-2 border-t border-blush/80 pt-5 text-sm">
                        <div class="flex justify-between">
                            <span class="text-ink/70">Subtotal</span>
                            <span x-text="rupiah(subtotal)"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-ink/70">Shipping</span>
                            <span x-text="method === 'pickup' ? 'Free' : (selected ? rupiah(shipping) : 'Select a service')"></span>
                        </div>
                        <div class="flex items-end justify-between pt-3">
                            <span class="font-semibold">Total</span>
                            <span class="text-2xl font-bold text-moss" x-text="rupiah(total)"></span>
                        </div>
                    </div>

                    <button class="btn-primary mt-6 w-full py-3.5 text-base disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="!canSubmit">Place order &amp; pay</button>
                    <p class="mt-3 text-center text-xs text-ink/50">Final shipping cost is confirmed by the server. Payment via Midtrans (card, QRIS, e-wallet).</p>
                </div>
            </div>
        </form>
    </div>
</x-public-layout>
