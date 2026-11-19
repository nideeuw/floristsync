<x-pos-layout title="History">
    <div x-data="{ openCloseModal: false, counted: '' }" class="h-[calc(100vh-3.5rem)] overflow-y-auto p-6">
        <div class="mx-auto max-w-5xl">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold">My Sales Today</h1>
                    <p class="text-sm text-gray-600">Reprint receipts and check your totals before closing the shift.</p>
                </div>
                @if ($shift)
                <div class="flex flex-wrap items-center gap-2">
                    <div class="inline-flex items-center gap-2 self-start rounded-full border border-sage/40 bg-cream px-3.5 py-1.5 text-xs text-moss shadow-sm sm:self-auto">
                        <span class="h-2 w-2 rounded-full {{ $shift->isOpen() ? 'bg-moss' : 'bg-gray-400' }}"></span>
                        <span>Shift #{{ $shift->id }} &bull; Opened {{ $shift->opened_at?->format('H:i') }}</span>
                        @if (! $shift->isOpen())
                        <span>&bull; Closed {{ $shift->closed_at?->format('H:i') }}</span>
                        @endif
                    </div>
                    @if ($shift->isOpen())
                    <button type="button" x-on:click="openCloseModal = true" class="btn-primary py-1.5 px-3 text-xs">
                        Close Shift
                    </button>
                    @endif
                </div>
                @endif
            </div>

            {{-- Close Summary (shown when shift is closed) --}}
            @if ($shift && ! $shift->isOpen())
            <div class="mt-5 rounded-2xl border border-blush bg-white p-5 shadow-sm">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-blush/60 pb-3 gap-2">
                    <div>
                        <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">Shift Reconciliation Summary</span>
                        <h2 class="text-lg font-bold text-ink">Shift #{{ $shift->id }} Closed</h2>
                    </div>
                    <div>
                        @if ($shift->cash_difference === null)
                        <span class="inline-block rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700">No count recorded</span>
                        @elseif ($shift->cash_difference === 0)
                        <span class="inline-block rounded-full bg-moss/10 border border-moss/30 px-3 py-1 text-xs font-bold text-moss">Exact Match &bull; Rp 0 Difference</span>
                        @elseif ($shift->cash_difference < 0)
                        <span class="inline-block rounded-full bg-red-100 border border-red-200 px-3 py-1 text-xs font-bold text-red-700">Cash Shortage &bull; Short by {{ rupiah(abs($shift->cash_difference)) }}</span>
                        @else
                        <span class="inline-block rounded-full bg-blue-100 border border-blue-200 px-3 py-1 text-xs font-bold text-blue-700">Cash Overage &bull; Over by {{ rupiah($shift->cash_difference) }}</span>
                        @endif
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6 text-xs">
                    <div>
                        <div class="text-gray-500 font-medium">Opening Cash</div>
                        <div class="mt-0.5 text-sm font-bold text-ink">{{ rupiah($shift->opening_cash ?? 0) }}</div>
                    </div>
                    <div>
                        <div class="text-gray-500 font-medium">Cash Sales</div>
                        <div class="mt-0.5 text-sm font-bold text-moss">+{{ rupiah($reconciliation['cash_sales'] ?? 0) }}</div>
                    </div>
                    <div>
                        <div class="text-gray-500 font-medium">Cancelled Cash</div>
                        <div class="mt-0.5 text-sm font-bold text-red-600">-{{ rupiah($reconciliation['cancelled_cash_sales'] ?? 0) }}</div>
                    </div>
                    <div>
                        <div class="text-gray-500 font-medium">Expected Cash</div>
                        <div class="mt-0.5 text-sm font-bold text-ink">{{ rupiah($reconciliation['expected_cash'] ?? 0) }}</div>
                    </div>
                    <div>
                        <div class="text-gray-500 font-medium">Counted Cash</div>
                        <div class="mt-0.5 text-sm font-bold text-ink">{{ $shift->closing_cash_counted !== null ? rupiah($shift->closing_cash_counted) : '-' }}</div>
                    </div>
                    <div>
                        <div class="text-gray-500 font-medium">Difference</div>
                        <div class="mt-0.5 text-sm font-extrabold {{ ($shift->cash_difference ?? 0) < 0 ? 'text-red-600' : (($shift->cash_difference ?? 0) > 0 ? 'text-blue-600' : 'text-moss') }}">
                            @if ($shift->cash_difference === null)
                            -
                            @elseif ($shift->cash_difference === 0)
                            Rp 0
                            @elseif ($shift->cash_difference < 0)
                            -{{ rupiah(abs($shift->cash_difference)) }}
                            @else
                            +{{ rupiah($shift->cash_difference) }}
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @endif

            {{-- Summary Cards --}}
            <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div class="card p-5">
                    <div class="text-sm text-gray-500">Transactions</div>
                    <div class="mt-1 text-2xl font-extrabold">{{ $summary->orders }}</div>
                </div>

                <div class="card p-5">
                    <div class="text-sm text-gray-500">Total sales</div>
                    <div class="mt-1 text-2xl font-extrabold">{{ rupiah($summary->revenue) }}</div>
                </div>

                @foreach (['cash' => 'Cash', 'qris' => 'QRIS', 'debit' => 'Debit'] as $key => $label)
                <div class="card p-5">
                    <div class="text-sm text-gray-500">{{ $label }}</div>
                    <div class="mt-1 text-2xl font-extrabold">{{ rupiah($byMethod->get($key)?->total ?? 0) }}</div>
                    <div class="mt-1 text-xs text-gray-500">{{ $byMethod->get($key)?->orders ?? 0 }} transaction(s)</div>
                </div>
                @endforeach
            </div>

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
                            <th class="px-4 py-3 text-right">Receipt</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-blush">
                        @forelse ($transactions as $transaction)
                        <tr>
                            <td class="px-4 py-3 font-mono text-xs">
                                <div>{{ $transaction->order_number }}</div>
                                @if ($transaction->user)
                                <div class="text-[11px] font-sans font-semibold text-moss">{{ $transaction->user->name }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ \Carbon\Carbon::parse($transaction->created_on)->format('H:i') }}</td>
                            <td class="px-4 py-3">{{ $transaction->details_count }}</td>
                            <td class="px-4 py-3 uppercase">{{ $transaction->payment?->payment_method ?? '-' }}</td>
                            <td class="px-4 py-3 font-semibold">{{ rupiah($transaction->total) }}</td>
                            <td class="px-4 py-3 text-right">
                                @if ($transaction->status === 'completed')
                                <a href="{{ route('cashier.receipt', $transaction) }}" target="_blank" rel="noopener"
                                    class="font-semibold text-moss hover:underline">Reprint</a>
                                @else
                                <x-status-badge :status="$transaction->status" />
                                @if ($transaction->status === 'cancelled' && $transaction->refund_method)
                                <div class="mt-0.5 text-[11px] text-gray-500 font-medium">
                                    Refund: <span class="uppercase">{{ str_replace('_', ' ', $transaction->refund_method) }}</span>
                                </div>
                                @endif
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                {{ $shift ? 'No sales yet in this shift.' : 'No sales yet today.' }}
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $transactions->links() }}</div>

            {{-- Cashier Shift History --}}
            @if (isset($recentShifts) && $recentShifts->isNotEmpty())
            <div class="mt-8">
                <h2 class="text-lg font-bold">My Shift History</h2>
                <p class="text-xs text-gray-500">Past shifts and cash reconciliation differences.</p>
                <div class="card mt-3 overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-blush/40 text-xs uppercase text-gray-600">
                            <tr>
                                <th class="px-4 py-3">Shift</th>
                                <th class="px-4 py-3">Opened</th>
                                <th class="px-4 py-3">Closed</th>
                                <th class="px-4 py-3">Opening Cash</th>
                                <th class="px-4 py-3">Counted Cash</th>
                                <th class="px-4 py-3 text-right">Cash Difference</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-blush">
                            @foreach ($recentShifts as $s)
                            <tr>
                                <td class="px-4 py-3 font-semibold">Shift #{{ $s->id }}</td>
                                <td class="px-4 py-3 text-xs">{{ $s->opened_at?->format('d M Y, H:i') }}</td>
                                <td class="px-4 py-3 text-xs">{{ $s->closed_at?->format('d M Y, H:i') ?? '-' }}</td>
                                <td class="px-4 py-3">{{ rupiah($s->opening_cash ?? 0) }}</td>
                                <td class="px-4 py-3">{{ $s->closing_cash_counted !== null ? rupiah($s->closing_cash_counted) : '-' }}</td>
                                <td class="px-4 py-3 text-right font-semibold">
                                    @if ($s->cash_difference === null)
                                    <span class="text-gray-400">Uncounted</span>
                                    @elseif ($s->cash_difference === 0)
                                    <span class="text-green-700 font-bold">Exact (Rp 0)</span>
                                    @elseif ($s->cash_difference < 0)
                                    <span class="text-red-600 font-bold">Short: -{{ rupiah(abs($s->cash_difference)) }}</span>
                                    @else
                                    <span class="text-blue-600 font-bold">Over: +{{ rupiah($s->cash_difference) }}</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
        </div>

        {{-- Close Shift Modal --}}
        @if ($shift && $shift->isOpen())
        <div x-show="openCloseModal" x-cloak x-transition.opacity
            class="fixed inset-0 z-50 flex items-center justify-center bg-ink/60 p-4 backdrop-blur-sm">
            <div class="card w-full max-w-md p-6 text-left ring-1 ring-blush/60 shadow-2xl">
                <div class="flex items-center justify-between border-b border-blush/70 pb-3">
                    <div>
                        <span class="eyebrow text-xs uppercase tracking-wider text-gray-500">Cash Drawer Reconciliation</span>
                        <h2 class="font-display text-xl font-bold">Close Shift #{{ $shift->id }}</h2>
                    </div>
                    <button type="button" x-on:click="openCloseModal = false" class="text-gray-400 hover:text-red-600 font-bold p-1">✕</button>
                </div>

                <div class="mt-4 rounded-xl bg-cream/50 p-3 space-y-1.5 text-xs">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Opening cash:</span>
                        <span class="font-semibold">{{ rupiah($reconciliation['opening_cash'] ?? 0) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Cash sales:</span>
                        <span class="font-semibold text-moss">+{{ rupiah($reconciliation['cash_sales'] ?? 0) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Cancelled cash sales:</span>
                        <span class="font-semibold text-red-600">-{{ rupiah($reconciliation['cancelled_cash_sales'] ?? 0) }}</span>
                    </div>
                    <div class="flex justify-between border-t border-blush pt-2 text-sm font-bold">
                        <span>Expected in drawer:</span>
                        <span class="text-moss">{{ rupiah($reconciliation['expected_cash'] ?? 0) }}</span>
                    </div>
                </div>

                <form method="POST" action="{{ route('cashier.shift.close') }}" class="mt-5 space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-gray-700">Counted Cash Amount (Rp)</label>
                        <input type="number" min="0" name="closing_cash_counted" x-model="counted"
                            placeholder="Enter amount counted in drawer" class="input mt-1 w-full" autofocus>
                        <p class="mt-1 text-[11px] text-gray-500">Leave empty if you want to close without counting.</p>

                        <div x-show="counted !== ''" class="mt-2 text-xs font-semibold">
                            <template x-if="Number(counted) === {{ (int) ($reconciliation['expected_cash'] ?? 0) }}">
                                <span class="text-green-700 font-bold">✓ Exact match (Rp 0 difference)</span>
                            </template>
                            <template x-if="Number(counted) < {{ (int) ($reconciliation['expected_cash'] ?? 0) }}">
                                <span class="text-red-600 font-bold">⚠ Short by <span x-text="new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format({{ (int) ($reconciliation['expected_cash'] ?? 0) }} - Number(counted))"></span></span>
                            </template>
                            <template x-if="Number(counted) > {{ (int) ($reconciliation['expected_cash'] ?? 0) }}">
                                <span class="text-blue-600 font-bold">ℹ Over by <span x-text="new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(counted) - {{ (int) ($reconciliation['expected_cash'] ?? 0) }})"></span></span>
                            </template>
                        </div>
                    </div>

                    <div class="flex gap-2 pt-2">
                        <button type="button" x-on:click="openCloseModal = false" class="btn-outline flex-1 py-2 text-sm">
                            Cancel
                        </button>
                        <button type="submit" class="btn-primary flex-1 py-2 text-sm">
                            Confirm & Close Shift
                        </button>
                    </div>
                </form>
            </div>
        </div>
        @endif
    </div>
</x-pos-layout>
