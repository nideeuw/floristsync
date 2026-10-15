@php
    $canViewOrders = app(\App\Services\MenuPermissionService::class)
        ->can(auth()->user()->role_id, 'admin.orders', 'view');
@endphp

<x-staff-layout title="Dashboard">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-stat-card label="Revenue today (store + online)" :value="rupiah($today['revenue'])" />
        <x-stat-card label="Orders today" :value="$today['orders']" />
        <x-stat-card label="Online orders to process" :value="$today['to_process']" hint="Paid, waiting for shipping or pickup" />
        <x-stat-card label="Low stock products" :value="$today['low_stock']"
            :hint="'Stock at or below ' . config('floristsync.low_stock_threshold')" />
        <x-stat-card label="Cancelled sales" :value="$cancelled" :hint="'Last ' . $days . ' days, not counted as revenue'" />
    </div>

    <div class="mt-6">
        @include('admin.partials.charts', ['days' => $days])
    </div>

    <div class="mt-6 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
        <div class="card p-5">
            <h2 class="mb-3 font-semibold">Online vs POS revenue (last {{ $days }} days)</h2>
            @if (array_sum($channels['data']) > 0)
            <div class="h-64"><canvas id="channelChart"></canvas></div>
            @else
            <p class="py-16 text-center text-sm text-gray-500">No sales in this period.</p>
            @endif
        </div>

        <div class="card p-5">
            <h2 class="mb-3 font-semibold">Payment methods (last {{ $days }} days)</h2>
            @if ($paymentMethods->isNotEmpty())
            <div class="h-64"><canvas id="paymentChart"></canvas></div>
            @else
            <p class="py-16 text-center text-sm text-gray-500">No sales in this period.</p>
            @endif
        </div>

        <div class="card overflow-hidden md:col-span-2 xl:col-span-1">
            <div class="border-b border-blush px-5 py-3 font-semibold">Top 5 products (last {{ $days }} days)</div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-blush/40 text-xs uppercase text-gray-600">
                        <tr>
                            <th class="px-5 py-3">Product</th>
                            <th class="px-5 py-3">Sold</th>
                            <th class="px-5 py-3 text-right">Revenue</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-blush">
                        @forelse ($topProducts as $row)
                        <tr>
                            <td class="px-5 py-3">{{ $row->product_name }}</td>
                            <td class="px-5 py-3">{{ $row->total_qty }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-right">{{ rupiah($row->total_revenue) }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="px-5 py-6 text-center text-gray-500">No sales in this period.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card mt-6 overflow-hidden">
        <div class="flex items-center justify-between border-b border-blush px-5 py-3">
            <h2 class="font-semibold">Recent Online Orders</h2>
            @if ($canViewOrders)
            <a href="{{ route('admin.orders') }}" class="text-sm font-semibold text-moss hover:underline">View all</a>
            @endif
        </div>

        <div class="overflow-x-auto">
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
                            @if ($canViewOrders)
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
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const colors = ['#7E8C69', '#F6B0BB', '#9CAD8C', '#F1C8CB', '#374030'];
            const rupiah = (v) => 'Rp ' + new Intl.NumberFormat('id-ID').format(v);
            const base = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
            };

            const channelCanvas = document.getElementById('channelChart');
            if (channelCanvas) {
                const channels = @json($channels);

                new Chart(channelCanvas, {
                    type: 'doughnut',
                    data: {
                        labels: channels.labels,
                        datasets: [{ data: channels.data, backgroundColor: ['#7E8C69', '#F6B0BB'] }],
                    },
                    options: {
                        ...base,
                        plugins: {
                            ...base.plugins,
                            tooltip: { callbacks: { label: (ctx) => ' ' + ctx.label + ': ' + rupiah(ctx.parsed) } },
                        },
                    },
                });
            }

            const paymentCanvas = document.getElementById('paymentChart');
            if (paymentCanvas) {
                const methods = @json($paymentMethods);

                new Chart(paymentCanvas, {
                    type: 'doughnut',
                    data: {
                        labels: methods.map((m) => m.label),
                        datasets: [{ data: methods.map((m) => m.orders), backgroundColor: colors }],
                    },
                    options: {
                        ...base,
                        plugins: {
                            ...base.plugins,
                            tooltip: {
                                callbacks: {
                                    label: (ctx) => ' ' + ctx.label + ': ' + ctx.parsed + ' orders (' + rupiah(methods[ctx.dataIndex].revenue) + ')',
                                },
                            },
                        },
                    },
                });
            }
        });
    </script>
</x-staff-layout>