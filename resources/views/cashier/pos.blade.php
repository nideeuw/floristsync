<x-pos-layout>
    <div x-data="pos"
        data-storage-url="{{ asset('storage') }}"
        data-low-stock="{{ (int) config('floristsync.low_stock_threshold') }}"
        data-login-url="{{ route('cashier.login') }}"
        x-on:keydown.window.f2.prevent="$refs.search.focus()"
        class="flex h-[calc(100vh-3.5rem)]">

        {{-- ================= Left: products ================= --}}
        <section class="flex min-w-0 flex-1 flex-col">
            <div class="space-y-3 border-b border-blush bg-white p-4">
                <div class="flex gap-3">
                    <input x-ref="search" x-model="search" type="text" placeholder="Search product (F2)" class="input"
                        x-on:keydown.enter="if (filtered.length === 1) { add(filtered[0]); search = ''; }">
                    <button type="button" x-on:click="load()" class="btn-outline shrink-0" title="Refresh stock">↻</button>
                </div>

                <div class="flex gap-2 overflow-x-auto pb-1">
                    <button type="button" x-on:click="categoryId = null"
                        :class="categoryId === null ? 'bg-moss text-white' : 'bg-blush/60 hover:bg-blush'"
                        class="shrink-0 rounded-full px-4 py-1.5 text-sm font-semibold">All</button>

                    <template x-for="c in categories" :key="c.id">
                        <button type="button" x-on:click="categoryId = c.id" x-text="c.category_name"
                            :class="categoryId === c.id ? 'bg-moss text-white' : 'bg-blush/60 hover:bg-blush'"
                            class="shrink-0 rounded-full px-4 py-1.5 text-sm font-semibold"></button>
                    </template>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto p-4">
                <div x-show="filtered.length === 0" x-cloak class="py-16 text-center text-gray-500">
                    <div class="text-5xl">🥀</div>
                    <p class="mt-2">No products found.</p>
                </div>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                    <template x-for="p in filtered" :key="p.id">
                        <button type="button" x-on:click="add(p)" :disabled="p.stock < 1"
                            class="card flex flex-col overflow-hidden text-left transition hover:border-petal hover:shadow-md active:scale-95 disabled:cursor-not-allowed disabled:opacity-40">
                            <div class="relative flex aspect-[4/3] items-center justify-center overflow-hidden"
                                :class="['bg-blush', 'bg-petal/50', 'bg-sage/40', 'bg-cream'][p.id % 4]">
                                <template x-if="p.image">
                                    <img :src="storageUrl + '/' + p.image" alt="" class="h-full w-full object-cover">
                                </template>
                                <template x-if="!p.image">
                                    <span class="text-5xl" x-text="emojis[p.id % 6]"></span>
                                </template>

                                <span class="absolute right-2 top-2 rounded-full px-2 py-0.5 text-xs font-semibold"
                                    :class="p.stock < 1 ? 'bg-gray-700 text-white' : (p.stock <= lowStock ? 'bg-red-100 text-red-700' : 'bg-white/90')"
                                    x-text="p.stock < 1 ? 'Sold out' : p.stock + ' left'"></span>
                            </div>

                            <div class="p-3">
                                <div class="line-clamp-2 text-sm font-semibold leading-snug" x-text="p.product_name"></div>
                                <div class="mt-1 font-bold text-moss" x-text="rupiah(p.price)"></div>
                            </div>
                        </button>
                    </template>
                </div>
            </div>
        </section>

        {{-- ================= Right: cart and payment ================= --}}
        <aside class="flex w-full max-w-sm shrink-0 flex-col border-l border-blush bg-white sm:w-96">
            <div class="flex items-center justify-between border-b border-blush px-4 py-3">
                <div>
                    <div class="font-bold">Current Order</div>
                    <div class="text-xs text-gray-500" x-text="itemCount + ' item(s)'"></div>
                </div>
                <button type="button" x-on:click="clear()" x-show="cart.length" x-cloak
                    class="text-sm text-red-600 hover:underline">Clear</button>
            </div>

            <div class="flex-1 space-y-3 overflow-y-auto p-4">
                <div x-show="cart.length === 0" x-cloak class="py-16 text-center text-gray-400">
                    <div class="text-5xl">🛒</div>
                    <p class="mt-2 text-sm">Tap a product to add it.</p>
                </div>

                <template x-for="item in cart" :key="item.product_id">
                    <div class="rounded-xl border border-blush p-3">
                        <div class="flex items-start justify-between gap-2">
                            <div class="text-sm font-semibold leading-snug" x-text="item.product_name"></div>
                            <button type="button" x-on:click="remove(item)" class="text-gray-400 hover:text-red-600" aria-label="Remove">✕</button>
                        </div>

                        <div class="mt-2 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <button type="button" x-on:click="dec(item)" class="h-8 w-8 rounded-full bg-blush font-bold hover:bg-petal">-</button>
                                <span class="w-6 text-center font-semibold" x-text="item.qty"></span>
                                <button type="button" x-on:click="inc(item)" :disabled="item.qty >= item.stock"
                                    class="h-8 w-8 rounded-full bg-blush font-bold hover:bg-petal disabled:opacity-40">+</button>
                            </div>
                            <div class="font-bold" x-text="rupiah(item.price * item.qty)"></div>
                        </div>
                    </div>
                </template>
            </div>

            <div class="space-y-3 border-t border-blush bg-cream/50 p-4">
                <div class="flex items-center justify-between text-lg font-extrabold">
                    <span>Total</span>
                    <span x-text="rupiah(total)"></span>
                </div>

                {{-- Payment method --}}
                <div class="grid grid-cols-3 gap-2">
                    <template x-for="m in [{ key: 'cash', label: 'Cash' }, { key: 'qris', label: 'QRIS' }, { key: 'debit', label: 'Debit' }]" :key="m.key">
                        <button type="button" x-on:click="method = m.key" x-text="m.label"
                            :class="method === m.key ? 'bg-moss text-white' : 'bg-white border border-blush hover:bg-blush/50'"
                            class="rounded-xl py-2 text-sm font-semibold"></button>
                    </template>
                </div>

                {{-- Cash --}}
                <div x-show="method === 'cash'" x-cloak class="space-y-2">
                    <input type="number" min="0" x-model="paid" placeholder="Cash received" class="input">

                    <div class="flex flex-wrap gap-2">
                        <button type="button" x-on:click="paid = total" :disabled="!cart.length"
                            class="rounded-full bg-blush/70 px-3 py-1 text-xs font-semibold hover:bg-blush disabled:opacity-40">Exact</button>
                        <template x-for="amount in [50000, 100000, 200000]" :key="amount">
                            <button type="button" x-on:click="paid = amount" x-text="rupiah(amount)"
                                class="rounded-full bg-blush/70 px-3 py-1 text-xs font-semibold hover:bg-blush"></button>
                        </template>
                    </div>

                    <div class="flex justify-between text-sm">
                        <span>Change</span>
                        <span class="font-bold" x-text="rupiah(change)"></span>
                    </div>
                </div>

                <p x-show="method !== 'cash'" x-cloak class="text-xs text-gray-600">
                    Confirm the customer has paid on the
                    <span x-text="method === 'qris' ? 'QRIS' : 'EDC / debit'"></span>
                    device, then complete the order.
                </p>

                <div x-show="error" x-cloak x-text="error" class="rounded-xl bg-red-100 px-3 py-2 text-sm text-red-800"></div>

                <button type="button" x-on:click="pay()" :disabled="!canPay" class="btn-primary w-full py-3 text-base">
                    <span x-text="loading ? 'Processing...' : 'Complete Order · ' + rupiah(total)"></span>
                </button>
            </div>
        </aside>

        {{-- ================= Success modal ================= --}}
        <div x-show="success" x-cloak x-transition.opacity
            class="fixed inset-0 z-50 flex items-center justify-center bg-ink/60 p-4">
            <div class="card w-full max-w-sm p-6 text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-moss text-2xl text-white">✓</div>
                <h2 class="mt-3 text-xl font-bold">Payment successful</h2>
                <p class="text-sm text-gray-500" x-text="success ? 'Order #' + success.id : ''"></p>

                <div class="mt-4 space-y-1 text-sm">
                    <div class="flex justify-between"><span>Total</span><span class="font-semibold" x-text="success ? rupiah(success.total) : ''"></span></div>
                    <template x-if="success && success.method === 'cash'">
                        <div class="space-y-1">
                            <div class="flex justify-between"><span>Cash received</span><span x-text="rupiah(success.paid)"></span></div>
                            <div class="flex justify-between text-lg font-extrabold text-moss"><span>Change</span><span x-text="rupiah(success.change)"></span></div>
                        </div>
                    </template>
                    <template x-if="success && success.method !== 'cash'">
                        <div class="flex justify-between"><span>Method</span><span class="uppercase" x-text="success.method"></span></div>
                    </template>
                </div>

                <div class="mt-6 grid grid-cols-2 gap-2">
                    <a :href="receiptUrl" target="_blank" rel="noopener" class="btn-outline">Print receipt</a>
                    <button type="button" x-on:click="newOrder()" class="btn-primary">New order</button>
                </div>
            </div>
        </div>
    </div>
</x-pos-layout>
