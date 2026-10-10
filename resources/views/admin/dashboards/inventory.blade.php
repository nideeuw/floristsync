<x-staff-layout title="Dashboard">
    <x-slot:actions>
        <a href="{{ route('admin.products.index') }}" class="btn-primary">Manage products</a>
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat-card label="Total products" :value="$totalProducts" />
        <x-stat-card label="Out of stock" :value="$outOfStock" />
        <x-stat-card label="Low stock" :value="$lowStock->count()"
            :hint="'At or below ' . config('floristsync.low_stock_threshold')" />
    </div>

    <div class="card mt-6 overflow-hidden">
        <div class="border-b border-blush px-5 py-3 font-semibold">
            Low Stock (at or below {{ config('floristsync.low_stock_threshold') }})
        </div>
        <table class="w-full text-left text-sm">
            <thead class="bg-blush/40 text-xs uppercase text-gray-600">
                <tr>
                    <th class="px-5 py-3">Product</th>
                    <th class="px-5 py-3">Stock</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-blush">
                @forelse ($lowStock as $product)
                <tr>
                    <td class="px-5 py-3">{{ $product->product_name }}</td>
                    <td class="px-5 py-3">
                        <span class="rounded-full bg-red-100 px-2 py-1 text-xs font-semibold text-red-700">{{ $product->stock }}</span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="2" class="px-5 py-6 text-center text-gray-500">All products have enough stock.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-staff-layout>
