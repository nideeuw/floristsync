<x-pos-layout title="Cancel Transaction">
    <div class="h-[calc(100vh-3.5rem)] overflow-y-auto p-6" x-data="{
        activeTx: null,
        openCancel(tx) {
            this.activeTx = tx;
        },
        closeCancel() {
            this.activeTx = null;
        }
    }">
        <div class="mx-auto max-w-5xl">
            {{-- Header --}}
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Cancel Transaction</h1>
                    <p class="text-sm text-gray-600">Cancel today's POS transactions directly and restore stock to inventory.</p>
                </div>
                @if ($shift)
                <div class="inline-flex items-center gap-2 self-start rounded-full border border-sage/40 bg-cream px-3.5 py-1.5 text-xs text-moss shadow-sm sm:self-auto">
                    <span class="h-2 w-2 rounded-full bg-moss"></span>
                    <span>Shift #{{ $shift->id }} &bull; Opened {{ $shift->opened_at?->format('H:i') }}</span>
                </div>
                @endif
            </div>

            <x-ui.flash class="mt-4" />

            {{-- Transactions Table --}}
            <div class="card mt-6 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-blush/40 text-xs uppercase text-gray-600">
                        <tr>
                            <th class="px-4 py-3">Order</th>
                            <th class="px-4 py-3">Time</th>
                            <th class="px-4 py-3">Items</th>
                            <th class="px-4 py-3">Payment</th>
                            <th class="px-4 py-3">Total</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-blush">
                        @forelse ($transactions as $transaction)
                        <tr class="{{ $transaction->status === 'cancelled' ? 'bg-gray-50/60' : 'hover:bg-cream/20' }} transition-colors">
                            <td class="px-4 py-3 font-mono text-xs font-semibold">
                                {{ $transaction->order_number }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-gray-600">
                                {{ \Carbon\Carbon::parse($transaction->created_on)->format('H:i') }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-800">{{ $transaction->details_count }} item(s)</div>
                                <div class="text-xs text-gray-500 max-w-xs truncate" title="{{ $transaction->details->map(fn($d) => "{$d->qty}x " . ($d->product?->product_name ?? 'Item'))->join(', ') }}">
                                    {{ $transaction->details->map(fn($d) => "{$d->qty}x " . ($d->product?->product_name ?? 'Item'))->join(', ') }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-block rounded bg-cream px-2 py-0.5 text-xs font-semibold uppercase text-ink/80 border border-blush/60">
                                    {{ $transaction->payment?->payment_method ?? '-' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-semibold whitespace-nowrap">
                                {{ rupiah($transaction->total) }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <x-status-badge :status="$transaction->status" />
                                @if ($transaction->status === 'cancelled' && $transaction->cancel_reason)
                                <div class="mt-1 max-w-[12rem] truncate text-xs text-red-600" title="{{ $transaction->cancel_reason }}">
                                    {{ $transaction->cancel_reason }}
                                </div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                @if ($transaction->status === 'completed')
                                <button type="button"
                                    @click="openCancel({
                                        id: {{ $transaction->id }},
                                        order: '{{ $transaction->order_number }}',
                                        total: '{{ rupiah($transaction->total) }}',
                                        action: '{{ route('cashier.cancel.store', $transaction) }}'
                                    })"
                                    class="inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50/70 px-3.5 py-1.5 text-xs font-semibold text-red-700 transition hover:bg-red-100 hover:border-red-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-400">
                                    <x-ui.icon name="x" class="h-3.5 w-3.5" />
                                    <span>Cancel</span>
                                </button>
                                @elseif ($transaction->status === 'cancelled')
                                <span class="text-xs text-gray-400">Cancelled</span>
                                @else
                                <span class="text-xs text-gray-400">-</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-gray-500">
                                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-cream text-moss mb-3">
                                    <x-ui.icon name="shopping-bag" class="h-6 w-6" />
                                </div>
                                <p class="font-medium text-gray-700">No sales recorded today.</p>
                                <p class="text-xs text-gray-500 mt-1">Completed transactions processed during your shift will appear here.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $transactions->links() }}</div>
        </div>

        {{-- Direct Cancellation Modal --}}
        <div x-show="activeTx" x-cloak
            class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0"
            aria-modal="true" role="dialog"
            x-on:keydown.escape.window="closeCancel()">
            {{-- Backdrop --}}
            <div x-show="activeTx" x-transition.opacity
                class="fixed inset-0 bg-ink/50 backdrop-blur-sm"
                @click="closeCancel()"></div>

            {{-- Dialog Box --}}
            <div x-show="activeTx"
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:scale-95"
                class="relative mx-auto mt-16 max-w-md rounded-2xl bg-white p-6 shadow-2xl border border-blush sm:mt-24">

                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Cancel POS Transaction</h3>
                        <p class="mt-0.5 font-mono text-xs text-gray-500" x-text="activeTx?.order"></p>
                    </div>
                    <button type="button" @click="closeCancel()"
                        class="rounded-xl p-1.5 text-gray-400 transition-colors hover:bg-cream hover:text-gray-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sage">
                        <x-ui.icon name="x" class="h-5 w-5" />
                    </button>
                </div>

                <div class="mt-4 rounded-xl border border-red-200/80 bg-red-50/80 p-3.5 text-xs text-red-900 leading-relaxed">
                    <p class="font-semibold text-red-800">Action is immediate and cannot be undone.</p>
                    <p class="mt-1 text-red-700">Stock will be returned to inventory automatically, and this transaction will be deducted from your shift revenue.</p>
                </div>

                <div class="mt-4 flex items-center justify-between border-y border-blush/70 py-2.5 text-sm">
                    <span class="text-gray-500">Transaction Total</span>
                    <span class="font-bold text-ink" x-text="activeTx?.total"></span>
                </div>

                <form method="POST" :action="activeTx?.action" class="mt-4 space-y-4">
                    @csrf
                    <div>
                        <label for="cancel_reason_input" class="block text-sm font-semibold text-gray-700">
                            Reason for Cancellation <span class="text-red-500">*</span>
                        </label>
                        <textarea name="reason" id="cancel_reason_input" rows="3" required maxlength="255"
                            class="input mt-1.5 w-full"
                            placeholder="Please provide a reason (e.g. customer returned item, incorrect payment method, cashier mistake)..."></textarea>
                        <p class="mt-1 text-[11px] text-gray-400">Reason is required and recorded in audit logs.</p>
                    </div>

                    <div class="flex items-center justify-end gap-2.5 pt-2">
                        <button type="button" @click="closeCancel()" class="btn-secondary">
                            Keep Transaction
                        </button>
                        <button type="submit"
                            class="inline-flex items-center justify-center gap-2 rounded-full bg-red-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-red-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500">
                            Confirm Cancellation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-pos-layout>
