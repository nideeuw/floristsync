<x-staff-layout title="Reports">
    <x-slot:actions>
        @foreach ([7, 30, 90] as $range)
        <a href="{{ route('admin.reports', ['days' => $range]) }}"
            class="{{ $days === $range ? 'btn-primary' : 'btn-outline' }}">{{ $range }} days</a>
        @endforeach
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card :label="'Revenue (last ' . $days . ' days)'" :value="rupiah($summary['revenue'])" />
        <x-stat-card label="Orders" :value="$summary['orders']" />
        <x-stat-card label="Average order value" :value="rupiah($summary['average'])" />
        <x-stat-card label="POS vs online" :value="rupiah($summary['pos_revenue'])"
            :hint="'Online: ' . rupiah($summary['online_revenue'])" />
    </div>

    <div class="mt-6">
        @include('admin.partials.charts', ['days' => $days])
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="card overflow-hidden">
            <div class="border-b border-blush px-5 py-3 font-semibold">Best Selling Products</div>
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
                        <td class="px-5 py-3 text-right">{{ rupiah($row->total_revenue) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="px-5 py-6 text-center text-gray-500">No sales in this period.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="card overflow-hidden">
            <div class="border-b border-blush px-5 py-3 font-semibold">
                Low Stock (at or below {{ config('floristsync.low_stock_threshold') }})
            </div>
            <table class="w-full text-left text-sm">
                <thead class="bg-blush/40 text-xs uppercase text-gray-600">
                    <tr>
                        <th class="px-5 py-3">Product</th>
                        <th class="px-5 py-3">Stock</th>
                        @if (auth()->user()->hasRole('admin'))
                        <th class="px-5 py-3 text-right">Action</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-blush">
                    @forelse ($lowStock as $product)
                    <tr>
                        <td class="px-5 py-3">{{ $product->product_name }}</td>
                        <td class="px-5 py-3">
                            <span class="rounded-full bg-red-100 px-2 py-1 text-xs font-semibold text-red-700">{{ $product->stock }}</span>
                        </td>
                        @if (auth()->user()->hasRole('admin'))
                        <td class="px-5 py-3 text-right">
                            <a href="{{ route('admin.products.edit', $product->id) }}" class="font-semibold text-moss hover:underline">Restock</a>
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="px-5 py-6 text-center text-gray-500">All products have enough stock.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-staff-layout>
