@php
$tabs = [
'' => 'All',
'pending_payment' => 'Pending payment',
'processing' => 'To process',
'ready_for_pickup' => 'Ready for pickup',
'shipped' => 'Shipped',
'completed' => 'Completed',
'cancelled' => 'Cancelled',
];
$current = (string) request('status', '');
@endphp

<x-staff-layout title="Online Orders">

    {{-- Status tabs --}}
    <div class="mb-4 flex flex-wrap gap-2">
        @foreach ($tabs as $key => $label)
        @php($count = $key === '' ? $counts->sum() : $counts->get($key, 0))

        <a href="{{ route('admin.orders', array_filter(['status' => $key, 'fulfillment' => request('fulfillment'), 'q' => request('q')])) }}"
            class="rounded-full px-4 py-1.5 text-sm font-semibold {{ $current === (string) $key ? 'bg-moss text-white' : 'bg-white hover:bg-blush' }}">
            {{ $label }}
            <span class="ml-1 text-xs opacity-80">{{ $count }}</span>
        </a>
        @endforeach
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.orders') }}" class="card mb-4 grid gap-3 p-4 md:grid-cols-4">
        <input type="hidden" name="status" value="{{ $current }}">

        <input type="text" name="q" value="{{ request('q') }}" placeholder="Order number or ID" class="input md:col-span-2">

        <select name="fulfillment" class="input">
            <option value="">Delivery and pickup</option>
            <option value="delivery" @selected(request('fulfillment')==='delivery' )>Delivery (courier)</option>
            <option value="pickup" @selected(request('fulfillment')==='pickup' )>Pickup in store</option>
        </select>

        <div class="flex gap-2">
            <button class="btn-primary">Filter</button>
            <a href="{{ route('admin.orders') }}" class="btn-outline">Reset</a>
        </div>
    </form>

    <div class="card overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-blush/40 text-xs uppercase text-gray-600">
                <tr>
                    <th class="px-4 py-3">Order</th>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Customer</th>
                    <th class="px-4 py-3">Fulfillment</th>
                    <th class="px-4 py-3">Total</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-blush">
                @forelse ($orders as $order)
                <tr>
                    <td class="px-4 py-3 font-mono text-xs">{{ $order->order_number }}</td>
                    <td class="px-4 py-3">{{ \Carbon\Carbon::parse($order->created_on)->format('d M Y, H:i') }}</td>
                    <td class="px-4 py-3">
                        <div>{{ $order->user?->name ?? 'Guest' }}</div>
                        @if ($order->isDelivery())
                        <div class="text-xs text-gray-500">To: {{ \Illuminate\Support\Str::before($order->shipping_address, ' (') }}</div>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @if ($order->isDelivery())
                        <div>Delivery &middot; {{ strtoupper($order->courier) }}</div>
                        <div class="font-mono text-xs text-gray-500">{{ $order->tracking_number ?? 'No tracking number yet' }}</div>
                        @else
                        Pickup in store
                        @endif
                    </td>
                    <td class="px-4 py-3">{{ rupiah($order->total) }}</td>
                    <td class="px-4 py-3"><x-status-badge :status="$order->status" /></td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('admin.orders.show', $order) }}" class="font-semibold text-moss hover:underline">
                            {{ $order->status === 'processing' ? 'Process' : 'View' }}
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-4 py-8 text-center text-gray-500">No orders found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $orders->links() }}</div>
</x-staff-layout>
