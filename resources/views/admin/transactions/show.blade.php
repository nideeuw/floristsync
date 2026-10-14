@php
$itemsTotal = $transaction->details->sum('subtotal');
$shipping = $transaction->total - $itemsTotal;
$isPos = $transaction->transaction_type === 'pos';
$isDelivery = $transaction->isDelivery();
@endphp

<x-staff-layout :title="'Transaction ' . $transaction->order_number">
    <x-slot:actions>
        <a href="{{ route('admin.transactions') }}" class="btn-outline">&larr; Back to transactions</a>
    </x-slot:actions>

    @if ($transaction->status === 'cancelled')
    <div class="mb-6 rounded-xl bg-red-100 px-5 py-4 text-sm text-red-800">
        <div class="font-semibold">This transaction was cancelled.</div>
        <dl class="mt-2 grid gap-2 sm:grid-cols-3">
            <div>
                <dt class="text-xs uppercase opacity-70">Reason</dt>
                <dd>{{ $transaction->cancel_reason ?? 'No reason given' }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase opacity-70">Cancelled by</dt>
                <dd>{{ $transaction->cancelledBy?->name ?? '-' }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase opacity-70">Cancelled at</dt>
                <dd>{{ $transaction->cancelled_at?->format('d M Y, H:i') ?? '-' }}</dd>
            </div>
        </dl>
        @if ($transaction->cancel_requested_at)
        <div class="mt-2 text-xs opacity-80">Cancellation requested at {{ $transaction->cancel_requested_at->format('d M Y, H:i') }}</div>
        @endif
    </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">

        {{-- Left: items, payments, delivery --}}
        <div class="space-y-6 lg:col-span-2">
            <div class="card overflow-hidden">
                <div class="border-b border-blush px-5 py-3 font-semibold">Items</div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[28rem] text-left text-sm">
                        <thead class="bg-blush/40 text-xs uppercase text-gray-600">
                            <tr>
                                <th class="px-5 py-3">Product</th>
                                <th class="px-5 py-3">Qty</th>
                                <th class="px-5 py-3">Unit price</th>
                                <th class="px-5 py-3 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-blush">
                            @foreach ($transaction->details as $detail)
                            <tr>
                                <td class="px-5 py-3">{{ $detail->product?->product_name ?? 'Deleted product' }}</td>
                                <td class="px-5 py-3">{{ $detail->qty }}</td>
                                <td class="px-5 py-3">{{ rupiah($detail->unit_price) }}</td>
                                <td class="px-5 py-3 text-right">{{ rupiah($detail->subtotal) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="space-y-1 border-t border-blush px-5 py-4 text-sm">
                    <div class="flex justify-between"><span>Items</span><span>{{ rupiah($itemsTotal) }}</span></div>
                    @if ($isDelivery)
                    <div class="flex justify-between"><span>Shipping</span><span>{{ $shipping > 0 ? rupiah($shipping) : 'Free' }}</span></div>
                    @endif
                    <div class="flex justify-between text-base font-bold"><span>Total</span><span>{{ rupiah($transaction->total) }}</span></div>
                </div>
            </div>

            <div class="card overflow-hidden">
                <div class="border-b border-blush px-5 py-3 font-semibold">Payments</div>
                @if ($transaction->payments->isEmpty())
                <p class="px-5 py-4 text-sm text-gray-500">No payment recorded.</p>
                @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[28rem] text-left text-sm">
                        <thead class="bg-blush/40 text-xs uppercase text-gray-600">
                            <tr>
                                <th class="px-5 py-3">Reference</th>
                                <th class="px-5 py-3">Method</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3">Paid at</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-blush">
                            @foreach ($transaction->payments->sortByDesc('id') as $payment)
                            <tr>
                                <td class="px-5 py-3 font-mono text-xs">{{ $payment->midtrans_order_id ?? '-' }}</td>
                                <td class="px-5 py-3">{{ strtoupper($payment->payment_method ?? '-') }}</td>
                                <td class="px-5 py-3">{{ $payment->payment_status ?? '-' }}</td>
                                <td class="px-5 py-3">{{ $payment->payment_date ? \Carbon\Carbon::parse($payment->payment_date)->format('d M Y, H:i') : '-' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>

            @if ($isDelivery)
            <div class="card p-5 text-sm">
                <div class="mb-3 font-semibold">Delivery by courier</div>
                <div class="font-semibold">{{ \Illuminate\Support\Str::before($transaction->shipping_address, "\n") }}</div>
                <p class="mt-1 whitespace-pre-line text-gray-700">{{ \Illuminate\Support\Str::after($transaction->shipping_address, "\n") }}</p>

                <div class="mt-4 grid gap-3 border-t border-blush pt-4 sm:grid-cols-2">
                    <div>
                        <div class="text-xs uppercase text-gray-500">Courier</div>
                        <div class="font-semibold uppercase">{{ $transaction->courier }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase text-gray-500">Tracking number (resi)</div>
                        <div class="font-mono font-semibold">{{ $transaction->tracking_number ?? 'Not set' }}</div>
                    </div>
                </div>
            </div>
            @elseif (! $isPos)
            <div class="card p-5 text-sm">
                <div class="mb-1 font-semibold">Pickup in store</div>
                <p class="text-gray-700">The customer collects this order at the store.</p>
            </div>
            @endif
        </div>

        {{-- Right: summary --}}
        <div class="space-y-6">
            <div class="card space-y-3 p-5 text-sm">
                <div class="flex items-center justify-between">
                    <span class="font-semibold">Status</span>
                    <x-status-badge :status="$transaction->status" />
                </div>
                <div class="flex justify-between"><span>Order</span><span class="font-mono text-xs">{{ $transaction->order_number }}</span></div>
                <div class="flex justify-between"><span>Type</span><span>{{ $isPos ? 'POS (in store)' : 'Online' }}</span></div>
                <div class="flex justify-between"><span>Date</span><span>{{ \Carbon\Carbon::parse($transaction->created_on)->format('d M Y, H:i') }}</span></div>
            </div>

            <div class="card space-y-3 p-5 text-sm">
                <div class="font-semibold">Member</div>
                @if ($transaction->user)
                <div>{{ $transaction->user->name }}</div>
                <div class="text-xs text-gray-500">{{ $transaction->user->email }}</div>
                @else
                <p class="text-gray-500">No member on this transaction.</p>
                @endif
            </div>

            <div class="card space-y-3 p-5 text-sm">
                <div class="font-semibold">Cashier and shift</div>
                @if ($transaction->cashier)
                <div class="flex justify-between"><span>Cashier</span><span>{{ $transaction->cashier->name }}</span></div>
                <div class="flex justify-between">
                    <span>Shift</span>
                    <span>{{ $transaction->shift ? 'Shift #' . $transaction->shift->id : '-' }}</span>
                </div>
                @else
                <p class="text-gray-500">Online order, no cashier or shift.</p>
                @endif
            </div>
        </div>
    </div>
</x-staff-layout>
