<x-staff-layout title="Stock">
    <x-slot:actions>
        <a href="{{ route('admin.stock.restock') }}" class="btn-primary">+ Restock</a>
        <a href="{{ route('admin.stock.adjust') }}" class="btn-outline">Adjustment</a>
    </x-slot:actions>

    <form method="GET" class="card mb-4 grid gap-3 p-4 md:grid-cols-3">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search product..." class="input">

        <select name="category" class="input">
            <option value="">All categories</option>
            @foreach ($categories as $category)
            <option value="{{ $category->id }}" @selected(request('category')==$category->id)>{{ $category->category_name }}</option>
            @endforeach
        </select>

        <div class="flex gap-2">
            <button class="btn-primary">Filter</button>
            <a href="{{ route('admin.stock.index') }}" class="btn-outline">Reset</a>
        </div>
    </form>

    <x-ui.table>
        <x-slot:head>
            <th>Product</th>
            <th>Category</th>
            <th>Stock</th>
            <th>Status</th>
            <th class="text-right">Actions</th>
        </x-slot:head>

        <x-slot:body>
            @php($threshold = config('floristsync.low_stock_threshold'))
            @forelse ($products as $product)
            <tr class="{{ $product->stock <= $threshold ? 'bg-red-50/60' : '' }}">
                <td>
                    <div class="flex items-center gap-3">
                        <x-product-image :product="$product" class="h-12 w-12 shrink-0 rounded-lg [&_svg]:h-6 [&_svg]:w-6" />
                        <span class="font-medium">{{ $product->product_name }}</span>
                    </div>
                </td>
                <td>{{ $product->category?->category_name }}</td>
                <td>
                    <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $product->stock <= $threshold ? 'bg-red-100 text-red-700' : 'bg-sage/30' }}">
                        {{ $product->stock }}
                    </span>
                </td>
                <td>
                    <span class="rounded-full px-2 py-1 text-xs {{ $product->is_active && $product->status === 'available' ? 'bg-sage/30' : 'bg-gray-200 text-gray-600' }}">
                        {{ ! $product->is_active ? 'Inactive' : ucfirst($product->status) }}
                    </span>
                </td>
                <td class="whitespace-nowrap text-right">
                    <a href="{{ route('admin.stock.restock', ['product' => $product->id]) }}" class="mr-3 font-semibold text-moss hover:underline">Restock</a>
                    <a href="{{ route('admin.stock.adjust', ['product' => $product->id]) }}" class="font-semibold text-moss hover:underline">Adjust</a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="!py-8 text-center text-gray-500">No products found.</td>
            </tr>
            @endforelse
        </x-slot:body>

        <x-slot:footer>{{ $products->links() }}</x-slot:footer>
    </x-ui.table>
</x-staff-layout>
