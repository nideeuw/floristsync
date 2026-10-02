<x-staff-layout title="Products">
    <x-slot:actions>
        <a href="{{ route('admin.products.create') }}" class="btn-primary">+ New Product</a>
    </x-slot:actions>

    <form method="GET" class="card mb-4 grid gap-3 p-4 md:grid-cols-4">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search product..." class="input">

        <select name="category" class="input">
            <option value="">All categories</option>
            @foreach ($categories as $category)
            <option value="{{ $category->id }}" @selected(request('category')==$category->id)>{{ $category->category_name }}</option>
            @endforeach
        </select>

        <select name="status" class="input">
            <option value="">All statuses</option>
            <option value="available" @selected(request('status')==='available' )>Available</option>
            <option value="unavailable" @selected(request('status')==='unavailable' )>Unavailable</option>
        </select>

        <div class="flex gap-2">
            <button class="btn-primary">Filter</button>
            <a href="{{ route('admin.products.index') }}" class="btn-outline">Reset</a>
        </div>
    </form>

    <div class="card overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-blush/40 text-xs uppercase text-gray-600">
                <tr>
                    <th class="px-4 py-3">Product</th>
                    <th class="px-4 py-3">Category</th>
                    <th class="px-4 py-3">Price</th>
                    <th class="px-4 py-3">Stock</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-blush">
                @forelse ($products as $product)
                <tr>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <x-product-image :product="$product" class="h-12 w-12 shrink-0 rounded-lg [&_span]:text-2xl" />
                            <span class="font-medium">{{ $product->product_name }}</span>
                        </div>
                    </td>
                    <td class="px-4 py-3">{{ $product->category?->category_name }}</td>
                    <td class="px-4 py-3">{{ rupiah($product->price) }}</td>
                    <td class="px-4 py-3">
                        <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $product->stock <= config('floristsync.low_stock_threshold') ? 'bg-red-100 text-red-700' : 'bg-sage/30' }}">
                            {{ $product->stock }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <span class="rounded-full px-2 py-1 text-xs {{ $product->is_active && $product->status === 'available' ? 'bg-sage/30' : 'bg-gray-200 text-gray-600' }}">
                            {{ ! $product->is_active ? 'Inactive' : ucfirst($product->status) }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('admin.products.edit', $product) }}" class="mr-3 font-semibold text-moss hover:underline">Edit</a>
                        <form method="POST" action="{{ route('admin.products.destroy', $product) }}" class="inline"
                            onsubmit="return confirm('Delete this product?')">
                            @csrf
                            @method('DELETE')
                            <button class="text-red-600 hover:underline">Delete</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">No products found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $products->links() }}</div>
</x-staff-layout>
