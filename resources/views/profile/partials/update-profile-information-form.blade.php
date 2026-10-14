<section>
    <script>
        function profileDestination(cfg) {
            return {
                id: cfg.id,
                label: cfg.label,
                results: [],
                searching: false,
                error: '',
                timer: null,
                requestId: 0,

                name(place) {
                    return place.label
                        || [place.subdistrict_name, place.district_name, place.city_name, place.province_name].filter(Boolean).join(', ');
                },

                onInput() {
                    this.id = null;
                    this.error = '';
                    clearTimeout(this.timer);

                    if (this.label.trim().length < 3) {
                        this.results = [];
                        return;
                    }

                    this.timer = setTimeout(() => this.search(), 400);
                },

                async search() {
                    const requestId = ++this.requestId;
                    this.searching = true;

                    try {
                        const res = await fetch(cfg.url + '?q=' + encodeURIComponent(this.label.trim()), {
                            headers: { 'Accept': 'application/json' },
                        });
                        const body = await res.json().catch(() => ({}));

                        if (requestId !== this.requestId) return;

                        if (!res.ok) {
                            throw new Error(body.message || 'Search failed. Please try again.');
                        }

                        this.results = body.data || [];
                    } catch (e) {
                        if (requestId !== this.requestId) return;
                        this.results = [];
                        this.error = e.message;
                    } finally {
                        if (requestId === this.requestId) this.searching = false;
                    }
                },

                pick(place) {
                    this.id = place.id;
                    this.label = this.name(place);
                    this.results = [];
                },
            };
        }
    </script>

    <header class="mb-6">
        <h2 class="font-display text-3xl font-semibold">Account &amp; shipping address</h2>
        <p class="mt-1 text-sm text-ink/60">This information is used to fill your checkout automatically.</p>
    </header>

    <form method="POST" action="{{ route('profile.update') }}" class="space-y-6">
        @csrf
        @method('PATCH')

        <div class="grid gap-4 md:grid-cols-2">
            <x-ui.field name="name" label="Full name" :value="$user->name" autocomplete="name" required />
            <x-ui.field name="phone" label="Phone" type="tel" :value="$user->phone" autocomplete="tel" />
            <div class="md:col-span-2">
                <x-ui.field name="email" label="Email" type="email" :value="$user->email" autocomplete="username" required />
            </div>
        </div>

        <div class="space-y-4 border-t border-blush/80 pt-6">
            <h3 class="flex items-center gap-2 font-semibold">
                <x-ui.icon name="map-pin" class="h-5 w-5 text-moss" />
                Shipping address
            </h3>

            <x-ui.textarea name="address" label="Full address" :value="$user->address" />

            <div class="grid gap-4 md:grid-cols-2">
                <div class="relative"
                    x-data="profileDestination({{ Js::from([
                        'id' => old('destination_id', $user->destination_id),
                        'label' => old('destination_label', $user->destination_label ?? ''),
                        'url' => url('/api/shipping/destinations'),
                    ]) }})"
                    @click.outside="results = []">
                    <label class="mb-1 block text-sm font-medium">City / district</label>
                    <input type="text" name="destination_label" class="input" autocomplete="off"
                        placeholder="Type at least 3 letters, then pick a place"
                        x-model="label" @input="onInput()">
                    <input type="hidden" name="destination_id" :value="id">

                    <p x-show="searching" x-cloak class="mt-1 text-xs text-ink/50">Searching...</p>
                    <p x-show="error" x-cloak x-text="error" class="mt-1 text-sm text-red-600"></p>
                    <p x-show="label.trim() && !id && !searching && !results.length" x-cloak class="mt-1 text-xs text-ink/50">
                        Pick a place from the list to save it.
                    </p>

                    <ul x-show="results.length" x-cloak
                        class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-2xl border border-blush bg-white shadow-xl">
                        <template x-for="place in results" :key="place.id">
                            <li>
                                <button type="button" @click="pick(place)"
                                    class="block w-full px-4 py-2.5 text-left text-sm hover:bg-blush/40"
                                    x-text="name(place)"></button>
                            </li>
                        </template>
                    </ul>

                    @if ($errors->has('destination_id') || $errors->has('destination_label'))
                    <p class="mt-1 text-sm text-red-600">Please pick a place from the search results.</p>
                    @endif
                </div>

                <x-ui.field name="postal_code" label="Postal code" :value="$user->postal_code" autocomplete="postal-code" />
            </div>
        </div>

        <div class="flex items-center gap-4">
            <button class="btn-primary px-7 py-3">Save changes</button>

            @if (session('status') === 'profile-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2500)"
                    class="inline-flex items-center gap-1 text-sm font-medium text-moss">
                    <x-ui.icon name="check" class="h-4 w-4" /> Saved.
                </p>
            @endif
        </div>
    </form>
</section>
