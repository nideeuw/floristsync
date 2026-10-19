<x-public-layout title="Cart">
    @php
        $jsItems = $items->map(fn ($item) => [
            'id' => (int) $item->product->id,
            'subtotal' => (int) $item->subtotal,
            'price' => (int) $item->product->price,
            'qty' => (int) $item->qty,
        ])->values();
    @endphp

    <div class="mx-auto max-w-7xl px-4 py-8 md:py-12"
        x-data="cartView({{ Js::from($jsItems) }})">
        <x-page-header eyebrow="Your selection" title="Your cart" />

        @if ($items->isEmpty())
        <x-ui.empty-state icon="shopping-cart" title="Your cart is empty"
            description="Add some flowers and they will show up here." class="mt-10">
            <a href="{{ route('catalog') }}" class="btn-primary">Browse flowers</a>
        </x-ui.empty-state>
        @else
        <div class="mt-10 grid gap-8 lg:grid-cols-3">
            {{-- Items --}}
            <div class="space-y-4 lg:col-span-2">
                {{-- Select-all bar --}}
                <div class="flex items-center justify-between rounded-[2rem] bg-white p-4 shadow-sm ring-1 ring-blush/60 sm:p-5">
                    <label class="flex cursor-pointer items-center gap-3 select-none">
                        <input type="checkbox" :checked="allSelected" @change="toggleAll()"
                            class="h-5 w-5 rounded border-blush text-moss focus:ring-sage accent-moss">
                        <span class="text-sm font-semibold">
                            Select all (<span x-text="selected.length"></span>/<span x-text="items.length"></span>)
                        </span>
                    </label>
                    <span class="text-xs text-ink/50" x-show="selected.length > 0" x-cloak>
                        <span x-text="selected.length"></span> item(s) selected
                    </span>
                </div>

                @foreach ($items as $item)
                <div class="flex items-center gap-3 rounded-[2rem] bg-white p-4 shadow-sm ring-1 ring-blush/60 sm:gap-5 sm:p-5">
                    {{-- Checkbox per item --}}
                    <label class="flex shrink-0 cursor-pointer items-center pr-1 select-none">
                        <input type="checkbox" :value="{{ $item->product->id }}" x-model.number="selected"
                            class="h-5 w-5 rounded border-blush text-moss focus:ring-sage accent-moss"
                            aria-label="Select {{ $item->product->product_name }}">
                    </label>

                    <a href="{{ route('products.show', $item->product) }}" class="shrink-0">
                        <x-product-image :product="$item->product" class="h-24 w-24 rounded-2xl sm:h-32 sm:w-32" />
                    </a>

                    <div class="flex min-w-0 flex-1 flex-col">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                @if ($item->product->category)
                                <span class="text-[11px] font-semibold uppercase tracking-widest text-moss">
                                    {{ $item->product->category->category_name }}
                                </span>
                                @endif
                                <a href="{{ route('products.show', $item->product) }}"
                                    class="block font-display text-xl font-semibold leading-snug transition hover:text-moss sm:text-2xl">
                                    {{ $item->product->product_name }}
                                </a>
                                <div class="text-sm text-ink/60">{{ rupiah($item->product->price) }} each</div>
                            </div>
                            <div class="shrink-0 text-right text-lg font-bold">{{ rupiah($item->subtotal) }}</div>
                        </div>

                        <div class="mt-auto flex flex-wrap items-end justify-between gap-3 pt-3">
                            <form method="POST" action="{{ route('cart.update', $item->product) }}"
                                class="flex items-center gap-2">
                                @csrf
                                @method('PATCH')
                                <input type="number" name="qty" value="{{ $item->qty }}" min="0"
                                    max="{{ $item->product->stock }}" class="input w-20 rounded-full text-center"
                                    aria-label="Quantity">
                                <button class="btn-outline px-4 py-2 text-xs">Update</button>
                            </form>

                            <form method="POST" action="{{ route('cart.remove', $item->product) }}">
                                @csrf
                                @method('DELETE')
                                <button class="inline-flex items-center gap-1 text-xs font-semibold text-red-600 transition hover:underline">
                                    <x-ui.icon name="trash-2" class="h-4 w-4" />
                                    Remove
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            {{-- Summary --}}
            <div>
                <div class="rounded-[2rem] bg-white p-6 shadow-sm ring-1 ring-blush/60 lg:sticky lg:top-24">
                    <h2 class="font-display text-3xl font-semibold">Order summary</h2>

                    <div class="mt-5 flex justify-between text-sm">
                        <span class="text-ink/70">Subtotal</span>
                        <span class="font-semibold" x-text="rupiah(selectedSubtotal)">{{ rupiah($subtotal) }}</span>
                    </div>
                    <p class="mt-1 text-xs text-ink/50">Shipping is calculated at checkout.</p>

                    <div class="mt-5 flex items-end justify-between border-t border-blush/80 pt-5">
                        <span class="font-semibold">Total</span>
                        <span class="text-2xl font-bold text-moss" x-text="rupiah(selectedSubtotal)">{{ rupiah($subtotal) }}</span>
                    </div>

                    <form method="GET" action="{{ route('checkout') }}" class="mt-6">
                        <template x-for="id in selected" :key="id">
                            <input type="hidden" name="selected[]" :value="id">
                        </template>

                        @auth
                        <button type="submit" :disabled="selected.length === 0"
                            class="btn-primary w-full py-3.5 text-base disabled:cursor-not-allowed disabled:opacity-50">
                            Proceed to checkout
                            <x-ui.icon name="arrow-right" class="h-4 w-4" />
                        </button>
                        @else
                        <button type="submit" :disabled="selected.length === 0"
                            class="btn-primary w-full py-3.5 text-base disabled:cursor-not-allowed disabled:opacity-50">
                            Log in to checkout
                        </button>
                        <p class="mt-3 text-center text-xs text-ink/60">
                            New here?
                            <a href="{{ route('register') }}" class="font-semibold text-moss hover:underline">Create an account</a>
                            to place your order. Your cart will be kept.
                        </p>
                        @endauth
                    </form>

                    <a href="{{ route('catalog') }}" class="btn-outline mt-3 w-full">Continue shopping</a>
                </div>
            </div>
        </div>
        @endif
    </div>

    <script>
        function cartView(itemsData) {
            return {
                items: itemsData,
                selected: itemsData.map(i => i.id),
                get allSelected() {
                    return this.items.length > 0 && this.selected.length === this.items.length;
                },
                toggleAll() {
                    if (this.allSelected) {
                        this.selected = [];
                    } else {
                        this.selected = this.items.map(i => i.id);
                    }
                },
                get selectedSubtotal() {
                    const sel = this.selected.map(Number);
                    return this.items
                        .filter(i => sel.includes(Number(i.id)))
                        .reduce((sum, i) => sum + i.subtotal, 0);
                },
                rupiah(n) {
                    return 'Rp ' + new Intl.NumberFormat('id-ID').format(n);
                }
            };
        }
    </script>
</x-public-layout>
