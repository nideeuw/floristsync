@php
$itemsTotal = $transaction->details->sum('subtotal');
$shipping = $transaction->total - $itemsTotal;
$isDelivery = $transaction->isDelivery();

// shipping_address is stored as: "Recipient name (phone)\nFull address"
$recipient = $isDelivery ? \Illuminate\Support\Str::before($transaction->shipping_address, "\n") : null;
$address = $isDelivery ? \Illuminate\Support\Str::after($transaction->shipping_address, "\n") : null;

$steps = ['Paid', $isDelivery ? 'Shipped' : 'Ready for pickup', 'Completed'];
$stepIndex = [
'pending_payment' => -1,
'processing' => 0,
'shipped' => 1,
'ready_for_pickup' => 1,
'completed' => 2,
][$transaction->status] ?? -1;
@endphp

<x-staff-layout :title="'Order ' . $transaction->order_number">
    <x-slot:actions>
        <a href="{{ route('admin.orders') }}" class="btn-outline">&larr; Back to orders</a>
    </x-slot:actions>

    {{-- Progress --}}
    @if ($transaction->status === 'cancelled')
    <div class="mb-6 rounded-xl bg-red-100 px-4 py-3 text-sm text-red-800">This order was cancelled. Stock has been returned.</div>
    @else
    <div class="card mb-6 p-5">
        <ol class="flex items-center">
            @foreach ($steps as $i => $label)
            <li class="flex flex-1 items-center gap-2 {{ $loop->last ? 'flex-none' : '' }}">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold {{ $i <= $stepIndex ? 'bg-moss text-white' : 'bg-blush text-gray-500' }}">
                    {{ $i <= $stepIndex ? '✓' : $i + 1 }}
                </span>
                <span class="text-sm {{ $i === $stepIndex ? 'font-semibold' : 'text-gray-500' }}">{{ $label }}</span>
                @unless ($loop->last)
                <span class="mx-2 h-0.5 flex-1 {{ $i < $stepIndex ? 'bg-moss' : 'bg-blush' }}"></span>
                @endunless
            </li>
            @endforeach
        </ol>
    </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">

        {{-- Left: items and shipping --}}
        <div class="space-y-6 lg:col-span-2">
            <div class="card overflow-hidden">
                <div class="border-b border-blush px-5 py-3 font-semibold">Items</div>
                <table class="w-full text-left text-sm">
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
                            <td class="px-5 py-3">{{ $detail->product->product_name }}</td>
                            <td class="px-5 py-3">{{ $detail->qty }}</td>
                            <td class="px-5 py-3">{{ rupiah($detail->unit_price) }}</td>
                            <td class="px-5 py-3 text-right">{{ rupiah($detail->subtotal) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="space-y-1 border-t border-blush px-5 py-4 text-sm">
                    <div class="flex justify-between"><span>Items</span><span>{{ rupiah($itemsTotal) }}</span></div>
                    <div class="flex justify-between"><span>Shipping</span><span>{{ $shipping > 0 ? rupiah($shipping) : 'Free' }}</span></div>
                    <div class="flex justify-between text-base font-bold"><span>Total</span><span>{{ rupiah($transaction->total) }}</span></div>
                </div>
            </div>

            {{-- Delivery or pickup --}}
            @if ($isDelivery)
            <div class="card p-5 text-sm" x-data="{ copied: false }">
                <div class="mb-3 flex items-center justify-between">
                    <div class="font-semibold">Delivery by courier</div>
                    <button type="button" class="text-xs font-semibold text-moss hover:underline"
                        @click="navigator.clipboard.writeText(@js($transaction->shipping_address)); copied = true; setTimeout(() => copied = false, 2000)">
                        <span x-text="copied ? 'Copied!' : 'Copy address'"></span>
                    </button>
                </div>

                <div class="font-semibold">{{ $recipient }}</div>
                <p class="mt-1 whitespace-pre-line text-gray-700">{{ $address }}</p>

                <div class="mt-4 grid gap-3 border-t border-blush pt-4 sm:grid-cols-2">
                    <div>
                        <div class="text-xs uppercase text-gray-500">Courier</div>
                        <div class="font-semibold uppercase">{{ $transaction->courier }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase text-gray-500">Tracking number (resi)</div>
                        <div class="font-mono font-semibold">{{ $transaction->tracking_number ?? 'Not set yet' }}</div>
                    </div>
                </div>
            </div>
            @else
            <div class="card p-5 text-sm">
                <div class="mb-1 font-semibold">Pickup in store</div>
                <p class="text-gray-700">The customer will collect this order at the store. No shipping is needed.</p>
            </div>
            @endif
        </div>

        {{-- Right: info and actions --}}
        <div class="space-y-6">
            <div class="card space-y-3 p-5 text-sm">
                <div class="flex items-center justify-between">
                    <span class="font-semibold">Status</span>
                    <x-status-badge :status="$transaction->status" />
                </div>
                <div class="flex justify-between"><span>Order</span><span class="font-mono text-xs">{{ $transaction->order_number }}</span></div>
                <div class="flex justify-between"><span>Date</span><span>{{ \Carbon\Carbon::parse($transaction->created_on)->format('d M Y, H:i') }}</span></div>
                <div class="flex justify-between"><span>Account</span><span>{{ $transaction->user?->name ?? 'Guest' }}</span></div>

                <div class="border-t border-blush pt-3 font-semibold">Payment</div>
                <div class="flex justify-between"><span>Method</span><span>{{ strtoupper($transaction->payment?->payment_method ?? '-') }}</span></div>
                <div class="flex justify-between"><span>Status</span><span>{{ $transaction->payment?->payment_status ?? '-' }}</span></div>
                <div class="flex justify-between"><span>Paid at</span>
                    <span>{{ $transaction->payment?->payment_date ? \Carbon\Carbon::parse($transaction->payment->payment_date)->format('d M Y, H:i') : '-' }}</span>
                </div>
            </div>

            <div class="card p-5">
                <div class="mb-3 font-semibold">Next step</div>

                @if ($transaction->status === 'pending_payment')
                <p class="text-sm text-gray-500">Waiting for the customer to pay. The status updates automatically once Midtrans confirms the payment.</p>

                @elseif ($transaction->status === 'processing' && $isDelivery)
                <p class="mb-3 text-sm text-gray-600">Pack the order and hand it to the courier, then enter the tracking number.</p>
                <form method="POST" action="{{ route('admin.orders.tracking', $transaction) }}" class="space-y-2">
                    @csrf
                    @method('PATCH')
                    <label class="block text-sm font-medium">Tracking number (resi)</label>
                    <input name="tracking_number" required class="input" placeholder="e.g. JNE1234567890">
                    <button class="btn-primary w-full">Save &amp; mark as shipped</button>
                </form>

                @elseif ($transaction->status === 'processing')
                <p class="mb-3 text-sm text-gray-600">Prepare the order, then let the customer know it is ready.</p>
                <form method="POST" action="{{ route('admin.orders.status', $transaction) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="ready_for_pickup">
                    <button class="btn-primary w-full">Mark as ready for pickup</button>
                </form>

                @elseif ($transaction->status === 'ready_for_pickup')
                <p class="mb-3 text-sm text-gray-600">Waiting for the customer to pick it up.</p>
                <form method="POST" action="{{ route('admin.orders.status', $transaction) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="completed">
                    <button class="btn-primary w-full">Mark as picked up (completed)</button>
                </form>

                @elseif ($transaction->status === 'shipped')
                <p class="mb-3 text-sm text-gray-600">The parcel is with the courier. Complete the order once it is delivered.</p>
                <form method="POST" action="{{ route('admin.orders.status', $transaction) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="completed">
                    <button class="btn-primary w-full">Mark as delivered (completed)</button>
                </form>

                @else
                <p class="text-sm text-gray-500">No further actions.</p>
                @endif
            </div>
        </div>
    </div>
</x-staff-layout>
