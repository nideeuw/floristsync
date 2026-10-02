@php
$product = $product ?? null;
$active = count(old()) ? (bool) old('is_active') : ($product->is_active ?? true);
@endphp

<div class="grid gap-4 md:grid-cols-2">
    <div class="md:col-span-2">
        <label class="mb-1 block text-sm font-medium">Product name</label>
        <input name="product_name" required class="input" value="{{ old('product_name', $product?->product_name) }}">
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium">Category</label>
        <select name="category_id" required class="input">
            <option value="">Select category</option>
            @foreach ($categories as $category)
            <option value="{{ $category->id }}" @selected(old('category_id', $product?->category_id) == $category->id)>
                {{ $category->category_name }}
            </option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium">Price (Rp)</label>
        <input type="number" name="price" min="0" required class="input" value="{{ old('price', $product?->price) }}">
    </div>

    @unless ($product)
    <div>
        <label class="mb-1 block text-sm font-medium">Initial stock</label>
        <input type="number" name="stock" min="0" class="input" value="{{ old('stock', 0) }}">
    </div>
    @endunless

    <div>
        <label class="mb-1 block text-sm font-medium">Status</label>
        <select name="status" class="input">
            <option value="available" @selected(old('status', $product?->status ?? 'available') === 'available')>Available</option>
            <option value="unavailable" @selected(old('status', $product?->status) === 'unavailable')>Unavailable</option>
        </select>
    </div>

    <div class="md:col-span-2">
        <label class="mb-1 block text-sm font-medium">Description</label>
        <textarea name="description" rows="3" class="input">{{ old('description', $product?->description) }}</textarea>
    </div>

    <div class="md:col-span-2">
        <label class="mb-1 block text-sm font-medium">Image (max 2 MB)</label>

        @if ($product?->image)
        <img src="{{ asset('storage/' . $product->image) }}" alt="" class="mb-2 h-24 w-24 rounded-xl object-cover">
        @endif

        <input type="file" name="image" accept="image/*" class="block text-sm">
    </div>

    <label class="flex items-center gap-2 text-sm md:col-span-2">
        <input type="checkbox" name="is_active" value="1" class="accent-moss" @checked($active)>
        Active (inactive products are hidden from customers and POS)
    </label>
</div>
