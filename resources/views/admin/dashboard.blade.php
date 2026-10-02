<x-staff-layout title="Dashboard">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Revenue today (store + online)" :value="rupiah($today['revenue'])" />
        <x-stat-card label="Orders today" :value="$today['orders']" />
        <x-stat-card label="Online orders to process" :value="$today['to_process']" hint="Paid, waiting for shipping or pickup" />
        <x-stat-card label="Low stock products" :value="$today['low_stock']"
            :hint="'Stock at or below ' . config('floristsync.low_stock_threshold')" />
    </div>

    <div class="mt-6">
        @include('admin.partials.charts', ['days' => 7])
    </div>

    <div class="card mt-6 overflow-hidden">
        <div class="flex items-center justify-between border-b border-blush px-5 py-3">
            <h2 class="font-semibold">Recent Online Orders</h2>
            @if (auth()->user()->hasRole('admin', 'cashier'))
            <a href="{{ route('admin.orders') }}" class="text-sm font-semibold text-moss hover:underline">View all</a>
            @endif
        </div>

        <table class="w-full text-left text-sm">
            <thead class="bg-blush/40 text-xs uppercase text-gray-600">
                <tr>
                    <th class="px-5 py-3">Order</th>
                    <th class="px-5 py-3">Customer</th>
                    <th class="px-5 py-3">Fulfillment</th>
                    <th class="px-5 py-3">Total</th>
                    <th class="px-5 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-blush">
                @forelse ($recentOrders as $order)
                <tr>
                    <td class="px-5 py-3 font-mono text-xs">
                        @if (auth()->user()->hasRole('admin', 'cashier'))
                        <a href="{{ route('admin.orders.show', $order) }}" class="font-semibold text-moss hover:underline">{{ $order->order_number }}</a>
                        @else
                        {{ $order->order_number }}
                        @endif
                    </td>
                    <td class="px-5 py-3">{{ $order->user?->name ?? 'Guest' }}</td>
                    <td class="px-5 py-3">
                        {{ $order->isDelivery() ? 'Delivery (' . strtoupper($order->courier) . ')' : 'Pickup' }}
                    </td>
                    <td class="px-5 py-3">{{ rupiah($order->total) }}</td>
                    <td class="px-5 py-3"><x-status-badge :status="$order->status" /></td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-5 py-6 text-center text-gray-500">No online orders yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-staff-layout>
