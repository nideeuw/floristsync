<x-staff-layout title="Order Milestones">
    <x-slot:actions>
        <a href="{{ route('admin.orders') }}" class="btn-outline">All orders</a>
    </x-slot:actions>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($columns as $column)
        <section class="card flex min-w-0 flex-col overflow-hidden">
            <header class="flex items-center justify-between gap-3 border-b border-blush px-4 py-3">
                <x-status-badge :status="$column['status']" />
                <span class="text-2xl font-bold">{{ $column['count'] }}</span>
            </header>

            <div class="px-4 pt-3 text-xs uppercase text-gray-500">{{ $column['label'] }}</div>

            @if ($column['orders']->isEmpty())
            <p class="px-4 py-8 text-center text-sm text-gray-500">No orders in this stage.</p>
            @else
            <ul class="divide-y divide-blush">
                @foreach ($column['orders'] as $order)
                <li>
                    <a href="{{ route('admin.orders.show', $order) }}" class="block min-w-0 px-4 py-3 hover:bg-blush/40">
                        <div class="truncate font-mono text-xs text-gray-600">{{ $order->order_number }}</div>
                        <div class="mt-0.5 flex items-center justify-between gap-2">
                            <span class="truncate text-sm font-semibold">{{ $order->user?->name ?? '-' }}</span>
                            <span class="whitespace-nowrap text-sm">{{ rupiah($order->total) }}</span>
                        </div>
                        <div class="mt-0.5 flex items-center justify-between gap-2 text-xs text-gray-500">
                            <span class="truncate">
                                @if ($order->isDelivery())
                                Delivery &middot; {{ strtoupper($order->courier) }}
                                @else
                                Pickup in store
                                @endif
                            </span>
                            <span class="whitespace-nowrap">{{ \Carbon\Carbon::parse($order->created_on)->format('d M Y, H:i') }}</span>
                        </div>
                    </a>
                </li>
                @endforeach
            </ul>
            @endif

            @if ($column['count'] > $column['orders']->count())
            <div class="mt-auto border-t border-blush px-4 py-3 text-right">
                <a href="{{ route('admin.orders', ['status' => $column['status']]) }}" class="text-sm font-semibold text-moss hover:underline">
                    View all {{ $column['count'] }} &rarr;
                </a>
            </div>
            @endif
        </section>
        @endforeach
    </div>
</x-staff-layout>