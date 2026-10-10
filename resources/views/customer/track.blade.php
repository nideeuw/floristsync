<x-public-layout title="Order History">
    <div class="mx-auto max-w-3xl px-4 py-10">
        <h1 class="text-3xl font-bold">Order History</h1>
        <p class="mt-1 text-sm text-gray-600">
            Enter your order number (for example <span class="font-mono">FS-12-AB12CD</span>).
            To preview this page, try <a href="{{ route('track', ['order' => 'DEMO']) }}" class="font-semibold text-moss hover:underline">DEMO</a>.
        </p>

        <form method="GET" action="{{ route('track') }}" class="card mt-6 flex gap-2 p-4">
            <input type="text" name="order" value="{{ $orderNumber }}" placeholder="Order number" class="input" required>
            <button class="btn-primary">Track</button>
        </form>

        @if ($orderNumber !== '' && ! $order)
        <x-ui.empty-state icon="search-x" title="Order not found"
            description="We could not find an order with that number." class="mt-6" />
        @endif

        @if ($order)
        @php
        $steps = [
        'Waiting for payment',
        'Preparing your order',
        $order['delivery'] ? 'Shipped' : 'Ready for pickup',
        'Completed',
        ];
        $current = [
        'pending_payment' => 0,
        'processing' => 1,
        'shipped' => 2,
        'ready_for_pickup' => 2,
        'completed' => 3,
        ][$order['status']] ?? 0;
        @endphp

        <div class="card mt-6 p-6">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <div class="text-xs uppercase tracking-wide text-gray-500">Order number</div>
                    <div class="font-mono font-semibold">{{ $order['number'] }}</div>
                </div>
                <div class="text-right">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Total</div>
                    <div class="font-bold">{{ rupiah($order['total']) }}</div>
                </div>
            </div>

            @if ($order['status'] === 'cancelled')
            <div class="mt-6 rounded-xl bg-red-100 px-4 py-3 text-sm text-red-800">This order was cancelled.</div>
            @else
            <ol class="mt-8 space-y-6">
                @foreach ($steps as $index => $label)
                <li class="flex items-center gap-4">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold
                                    {{ $index <= $current ? 'bg-moss text-white' : 'bg-blush text-gray-500' }}">
                        @if ($index
                        <= $current)
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

            @if ($order['delivery'] && $order['tracking_number'])
            <div class="mt-6 rounded-xl bg-cream px-4 py-3 text-sm">
                Courier: <span class="font-semibold uppercase">{{ $order['courier'] }}</span> &middot;
                Tracking number: <span class="font-mono font-semibold">{{ $order['tracking_number'] }}</span>
            </div>
            @endif

            <div class="mt-6 border-t border-blush pt-4">
                <div class="mb-2 text-sm font-semibold">Items</div>
                <ul class="space-y-1 text-sm">
                    @foreach ($order['items'] as $item)
                    <li class="flex justify-between">
                        <span>{{ $item['qty'] }} x {{ $item['name'] }}</span>
                        <span>{{ rupiah($item['subtotal']) }}</span>
                    </li>
                    @endforeach
                </ul>
            </div>
        </div>
        @endif
    </div>
</x-public-layout>
