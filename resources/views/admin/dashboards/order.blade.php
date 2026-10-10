<x-staff-layout title="Dashboard">
    <x-slot:actions>
        <a href="{{ route('admin.orders') }}" class="btn-primary">All orders</a>
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Online orders" :value="$total" />
        @foreach ($byPaymentStatus as $status => $count)
        <x-stat-card :label="str($status)->headline()" :value="$count" />
        @endforeach
    </div>

    <div class="card mt-6 overflow-hidden">
        <div class="border-b border-blush px-5 py-3 font-semibold">Latest Online Orders</div>
        <table class="w-full text-left text-sm">
            <thead class="bg-blush/40 text-xs uppercase text-gray-600">
                <tr>
                    <th class="px-5 py-3">Order</th>
                    <th class="px-5 py-3">Customer</th>
                    <th class="px-5 py-3">Payment</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-blush">
                @forelse ($recentOrders as $order)
                <tr>
                    <td class="px-5 py-3">#{{ $order->id }}</td>
                    <td class="px-5 py-3">{{ $order->user?->name ?? '-' }}</td>
                    <td class="px-5 py-3">{{ str((string) ($order->payment?->payment_status ?? 'unknown'))->headline() }}</td>
                    <td class="px-5 py-3 text-right">
                        <a href="{{ route('admin.orders.show', $order) }}" class="btn-outline">View</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-5 py-6 text-center text-gray-500">No online orders yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-staff-layout>
