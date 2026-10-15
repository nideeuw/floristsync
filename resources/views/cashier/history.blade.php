<x-pos-layout title="History">
    <div class="h-[calc(100vh-3.5rem)] overflow-y-auto p-6">
        <div class="mx-auto max-w-5xl">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold">My Sales Today</h1>
                    <p class="text-sm text-gray-600">Reprint receipts and check your totals before closing the shift.</p>
                </div>
                @if ($shift)
                <div class="inline-flex items-center gap-2 self-start rounded-full border border-sage/40 bg-cream px-3.5 py-1.5 text-xs text-moss shadow-sm sm:self-auto">
                    <span class="h-2 w-2 rounded-full bg-moss"></span>
                    <span>Shift #{{ $shift->id }} &bull; Opened {{ $shift->opened_at?->format('H:i') }}</span>
                </div>
                @endif
            </div>

            {{-- Summary --}}
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

            {{-- Transactions --}}
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
                            <td class="px-4 py-3 font-mono text-xs">{{ $transaction->order_number }}</td>
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
        </div>
    </div>
</x-pos-layout>
