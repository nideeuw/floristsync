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
                {{-- Optional Extras: Member & Special Note --}}
                <div class="space-y-2 border-b border-blush/60 pb-3">
                    {{-- Member selector --}}
                    <div>
                        {{-- Member selected badge --}}
                        <div x-show="member" x-cloak class="flex items-center justify-between rounded-xl border border-moss/30 bg-moss/10 px-3 py-2 text-xs">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-moss text-white font-bold text-[11px]">✓</span>
                                <div class="truncate">
                                    <div class="font-bold text-moss truncate" x-text="member ? member.name : ''"></div>
                                    <div class="text-[11px] text-gray-600 truncate" x-text="member ? ('ID #' + member.id + (member.phone ? ' · ' + member.phone : '')) : ''"></div>
                                </div>
                            </div>
                            <button type="button" x-on:click="removeMember()" class="ml-2 text-gray-400 hover:text-red-600 font-bold p-1" title="Remove member">✕</button>
                        </div>

                        {{-- Member search input --}}
                        <div x-show="!member" class="relative">
                            <div class="flex gap-1.5">
                                <div class="relative flex-1">
                                    <input type="text"
                                        x-model="memberQuery"
                                        x-ref="memberInput"
                                        x-on:input.debounce.250ms="searchMember()"
                                        x-on:keydown.enter.prevent="if (memberResults.length === 1) { selectMember(memberResults[0]); } else { searchMember(); }"
                                        placeholder="Member phone or ID (optional)"
                                        class="input py-1.5 text-xs">
                                    <div x-show="memberSearching" class="absolute right-2.5 top-2">
                                        <span class="inline-block h-3.5 w-3.5 animate-spin rounded-full border-2 border-moss border-t-transparent"></span>
                                    </div>
                                </div>
                                <button type="button" x-on:click="searchMember()" class="btn-outline px-2.5 py-1.5 text-xs shrink-0" title="Find member">
                                    Find
                                </button>
                            </div>

                            {{-- Dropdown results --}}
                            <div x-show="memberResults.length > 0" x-cloak x-on:click.outside="memberResults = []"
                                class="absolute left-0 right-0 z-20 mt-1 max-h-48 overflow-y-auto rounded-xl border border-blush bg-white p-1.5 shadow-lg">
                                <template x-for="m in memberResults" :key="m.id">
                                    <button type="button" x-on:click="selectMember(m)"
                                        class="flex w-full items-center justify-between rounded-lg px-2.5 py-1.5 text-left text-xs transition hover:bg-blush/40">
                                        <div class="truncate pr-2">
                                            <div class="font-semibold text-ink truncate" x-text="m.name"></div>
                                            <div class="text-[10px] text-gray-500" x-text="'ID #' + m.id + (m.phone ? ' · ' + m.phone : '')"></div>
                                        </div>
                                        <span class="rounded bg-moss/10 px-1.5 py-0.5 text-[10px] font-semibold text-moss shrink-0">Select</span>
                                    </button>
                                </template>
                            </div>
                            <div x-show="memberSearched && memberResults.length === 0 && memberQuery.trim() !== '' && !memberSearching" x-cloak
                                class="absolute left-0 right-0 z-20 mt-1 rounded-xl border border-blush bg-white p-2.5 text-center text-xs text-gray-500 shadow-lg">
                                No member found.
                            </div>
                        </div>
                    </div>

                    {{-- Card message / note --}}
                    <div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-semibold text-gray-600">Special Note / Card Message</span>
                            <button type="button" x-show="!showNote && !notes" x-on:click="showNote = true; $nextTick(() => $refs.noteInput?.focus())"
                                class="text-[11px] font-semibold text-moss hover:underline">+ Add note</button>
                            <button type="button" x-show="showNote || notes" x-on:click="notes = ''; showNote = false"
                                class="text-[11px] text-red-500 hover:underline">Clear</button>
                        </div>
                        <div x-show="showNote || notes" x-cloak class="mt-1">
                            <textarea x-ref="noteInput" x-model="notes" rows="2" maxlength="500"
                                placeholder="E.g. Card message: Happy Birthday Mom! Love, Jessica"
                                class="input py-1.5 text-xs leading-relaxed resize-none"></textarea>
                        </div>
                    </div>
                </div>

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

                <p x-show="method === 'debit'" x-cloak class="text-xs text-gray-600">
                    Confirm the customer has paid on the EDC / debit device, then complete the order.
                </p>
                <p x-show="method === 'qris'" x-cloak class="text-xs text-gray-600">
                    Generates an interactive QRIS code via Midtrans on screen for the customer to scan.
                </p>

                <div x-show="error" x-cloak x-text="error" class="rounded-xl bg-red-100 px-3 py-2 text-sm text-red-800"></div>

                <button type="button" x-on:click="pay()" :disabled="!canPay" class="btn-primary w-full py-3 text-base">
                    <span x-text="loading ? 'Processing...' : (method === 'qris' ? 'Generate QRIS · ' + rupiah(total) : 'Complete Order · ' + rupiah(total))"></span>
                </button>
            </div>
        </aside>

        {{-- ================= QRIS Payment modal ================= --}}
        <div x-show="qris" x-cloak x-transition.opacity
            class="fixed inset-0 z-50 flex items-center justify-center bg-ink/60 p-4 backdrop-blur-sm">
            <div class="card w-full max-w-md p-6 text-center ring-1 ring-blush/60 shadow-2xl">
                <div class="flex items-center justify-between border-b border-blush/70 pb-3">
                    <div class="text-left">
                        <span class="eyebrow">QRIS Payment</span>
                        <h2 class="font-display text-2xl font-bold" x-text="qris ? 'Order #' + qris.id : 'Order'"></h2>
                    </div>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-petal/40 px-3 py-1 text-xs font-semibold text-ink">
                        <x-ui.icon name="clock" class="h-3.5 w-3.5" />
                        <span>Pay via QRIS</span>
                    </span>
                </div>

                {{-- Total amount --}}
                <div class="mt-4 flex items-baseline justify-between rounded-xl bg-cream/50 px-4 py-2.5">
                    <span class="text-sm text-ink/70">Total Payment</span>
                    <span class="text-2xl font-bold text-moss" x-text="qris ? rupiah(qris.total) : ''"></span>
                </div>

                {{-- QR Code View --}}
                <x-qr-display class="mt-4" />

                {{-- Action buttons --}}
                <div class="mt-6 flex gap-2">
                    <button type="button" x-on:click="cancelQris()" class="btn-outline w-full py-2.5 text-sm">
                        Cancel Payment
                    </button>
                    <button type="button" x-on:click="startQrisPolling(qris.id)" x-show="qris && qris.status === 'pending'" class="btn-primary w-full py-2.5 text-sm">
                        Refresh Status
                    </button>
                </div>
            </div>
        </div>

        {{-- ================= Success modal ================= --}}
        <div x-show="success" x-cloak x-transition.opacity
            class="fixed inset-0 z-50 flex items-center justify-center bg-ink/60 p-4">
            <div class="card w-full max-w-sm p-6 text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-moss text-2xl text-white">✓</div>
                <h2 class="mt-3 text-xl font-bold">Payment successful</h2>
                <p class="text-sm text-gray-500" x-text="success ? 'Order #' + success.id : ''"></p>

                <div class="mt-4 space-y-1 text-sm">
                    <div class="flex justify-between"><span>Total</span><span class="font-semibold" x-text="success ? rupiah(success.total) : ''"></span></div>
                    <template x-if="success && success.member">
                        <div class="flex justify-between">
                            <span>Customer</span>
                            <span class="font-semibold text-moss" x-text="success.member.name"></span>
                        </div>
                    </template>
                    <template x-if="success && success.method === 'cash'">
                        <div class="space-y-1">
                            <div class="flex justify-between"><span>Cash received</span><span x-text="rupiah(success.paid)"></span></div>
                            <div class="flex justify-between text-lg font-extrabold text-moss"><span>Change</span><span x-text="rupiah(success.change)"></span></div>
                        </div>
                    </template>
                    <template x-if="success && success.method !== 'cash'">
                        <div class="flex justify-between"><span>Method</span><span class="uppercase" x-text="success.method"></span></div>
                    </template>
                    <template x-if="success && success.notes">
                        <div class="mt-2 rounded-lg bg-blush/30 p-2.5 text-left text-xs">
                            <span class="font-bold text-gray-600">Note:</span>
                            <div class="italic text-gray-800 whitespace-pre-line mt-0.5" x-text="success.notes"></div>
                        </div>
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
