<x-public-layout title="Order History">
    @php
        $statusIndex = [
            'pending_payment' => 0,
            'processing' => 1,
            'shipped' => 2,
            'ready_for_pickup' => 2,
            'completed' => 3,
        ];
        $cancellable = [\App\Models\Transaction::PENDING_PAYMENT, \App\Models\Transaction::PROCESSING];
    @endphp

    <div class="mx-auto max-w-3xl px-4 py-10">
        <h1 class="text-3xl font-bold">Order History</h1>
        <p class="mt-1 text-sm text-gray-600">Your orders, newest first. Pick an order to see its status.</p>

        {{-- Selected order: status timeline --}}
        @if ($orderNumber !== '' && ! $selected)
        <x-ui.empty-state icon="search-x" title="Order not found"
            description="We could not find that order in your account." class="mt-6" />
        @endif

        @if ($selected)
        @php
            $steps = [
                'Waiting for payment',
                'Preparing your order',
                $selected->isDelivery() ? 'Shipped' : 'Ready for pickup',
                'Completed',
            ];
            $current = $statusIndex[$selected->status] ?? 0;
            $manifest = is_array($tracking) ? ($tracking['manifest'] ?? []) : [];
        @endphp

        <div class="card mt-6 p-6">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <div class="text-xs uppercase tracking-wide text-gray-500">Order number</div>
                    <div class="font-mono font-semibold">{{ $selected->order_number }}</div>
                </div>
                <div class="text-right">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Total</div>
                    <div class="font-bold">{{ rupiah($selected->total) }}</div>
                </div>
            </div>

            @if ($selected->status === 'cancelled')
            <div class="mt-6 rounded-xl bg-red-100 px-4 py-3 text-sm text-red-800">This order was cancelled.</div>
            @else
            <ol class="mt-8 space-y-6">
                @foreach ($steps as $index => $label)
                <li class="flex items-center gap-4">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold
                                {{ $index <= $current ? 'bg-moss text-white' : 'bg-blush text-gray-500' }}">
                        @if ($index <= $current)
                        <x-ui.icon name="check" class="h-4 w-4" />
                        @else
                        {{ $index + 1 }}
                        @endif
                    </span>
                    <span class="{{ $index === $current ? 'font-semibold' : ($index < $current ? '' : 'text-gray-400') }}">
                        {{ $label }}
                    </span>
                </li>
                @endforeach
            </ol>
            @endif

            @if ($selected->isDelivery() && $selected->tracking_number)
            <div class="mt-6 rounded-xl bg-cream px-4 py-3 text-sm">
                Courier: <span class="font-semibold uppercase">{{ $selected->courier }}</span> &middot;
                Tracking number: <span class="font-mono font-semibold">{{ $selected->tracking_number }}</span>
            </div>

            @if (count($manifest))
            <ul class="mt-3 space-y-2 text-sm">
                @foreach ($manifest as $row)
                <li class="rounded-xl border border-blush px-4 py-2">
                    <div class="text-xs text-gray-500">
                        {{ data_get($row, 'manifest_date') }} {{ data_get($row, 'manifest_time') }}
                    </div>
                    <div>{{ data_get($row, 'manifest_description') }}</div>
                </li>
                @endforeach
            </ul>
            @endif
            @endif

            <div class="mt-6 border-t border-blush pt-4">
                <div class="mb-2 text-sm font-semibold">Items</div>
                <ul class="space-y-1 text-sm">
                    @foreach ($selected->details as $detail)
                    <li class="flex justify-between gap-3">
                        <span>{{ $detail->qty }} x {{ $detail->product->product_name }}</span>
                        <span class="shrink-0">{{ rupiah($detail->subtotal) }}</span>
                    </li>
                    @endforeach
                </ul>
            </div>
        </div>
        @endif

        {{-- Own orders --}}
        <div class="mt-8 space-y-3">
            @forelse ($orders as $order)
            <div class="card flex flex-wrap items-center justify-between gap-3 p-4
                        {{ $selected && $selected->id === $order->id ? 'ring-2 ring-sage/40' : '' }}">
                <div>
                    <div class="font-mono text-sm font-semibold">{{ $order->order_number }}</div>
                    <div class="text-xs text-gray-600">
                        {{ $order->created_on?->format('d M Y H:i') }} &middot;
                        <span class="capitalize">{{ str_replace('_', ' ', $order->status) }}</span> &middot;
                        <span class="capitalize">{{ $order->fulfillment }}</span>
                    </div>
                    <div class="mt-1 text-sm font-semibold">{{ rupiah($order->total) }}</div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('track', ['order' => $order->order_number, 'page' => $orders->currentPage()]) }}"
                        class="btn-primary">View status</a>

                    @if (in_array($order->status, $cancellable, true))
                        @if ($order->cancel_requested_at)
                        <span class="text-xs text-gray-600">Cancellation requested</span>
                        @else
                        <form method="POST" action="{{ route('track.cancel', $order) }}"
                            onsubmit="return confirm('Cancel this order?')">
                            @csrf
                            <button class="rounded-xl border border-blush px-4 py-2 text-sm font-semibold text-red-700 hover:bg-blush/40">
                                Cancel order
                            </button>
                        </form>
                        @endif
                    @endif
                </div>
            </div>
            @empty
            <x-ui.empty-state icon="search-x" title="No orders yet"
                description="Your orders will show up here after you check out." />
            @endforelse
        </div>

        <div class="mt-6">{{ $orders->links() }}</div>
    </div>
</x-public-layout>
