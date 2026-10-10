<x-public-layout title="Catalog">
    <div class="mx-auto max-w-7xl px-4 py-10">
        <h1 class="text-3xl font-bold">Catalog</h1>

        {{-- Filters --}}
        <form method="GET" action="{{ route('catalog') }}" class="card mt-6 grid gap-3 p-4 md:grid-cols-4">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Search flowers..."
                class="input md:col-span-2">

            <select name="category" class="input">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected(request('category')==$category->id)>
                    {{ $category->category_name }}
                </option>
                @endforeach
            </select>

            <select name="sort" class="input">
                <option value="newest" @selected(request('sort', 'newest' )==='newest' )>Newest</option>
                <option value="price_asc" @selected(request('sort')==='price_asc' )>Price: low to high</option>
                <option value="price_desc" @selected(request('sort')==='price_desc' )>Price: high to low</option>
                <option value="name" @selected(request('sort')==='name' )>Name (A-Z)</option>
            </select>

            <div class="flex gap-2 md:col-span-4">
                <button class="btn-primary">Apply</button>
                <a href="{{ route('catalog') }}" class="btn-outline">Reset</a>
            </div>
        </form>

        {{-- Products --}}
        @if ($products->isEmpty())
        <x-ui.empty-state icon="flower" title="No flowers found" description="Try another search or reset the filters."
            class="mt-8" />
        @else
        <div class="mt-8 grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
            @foreach ($products as $product)
            <x-product-card :product="$product" />
            @endforeach
        </div>

        <div class="mt-8">{{ $products->links() }}</div>
        @endif
    </div>
</x-public-layout>
