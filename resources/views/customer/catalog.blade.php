<x-public-layout title="Catalog">
    <div class="mx-auto max-w-7xl px-4 py-8 md:py-12">
        <x-page-header eyebrow="Our flowers" title="Catalog"
            description="Bouquets, flower boxes and standing flowers for every special moment." />

        {{-- Filters --}}
        <form method="GET" action="{{ route('catalog') }}"
            class="mt-8 rounded-[2rem] bg-white p-4 shadow-sm ring-1 ring-blush/60 md:p-5">
            <div class="grid gap-3 md:grid-cols-12">
                <div class="relative md:col-span-5">
                    <x-ui.icon name="search" class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-ink/40" />
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Search flowers..."
                        class="input rounded-full pl-11">
                </div>

                <select name="category" class="input rounded-full md:col-span-3">
                    <option value="">All categories</option>
                    @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(request('category')==$category->id)>
                        {{ $category->category_name }}
                    </option>
                    @endforeach
                </select>

                <select name="sort" class="input rounded-full md:col-span-2">
                    <option value="newest" @selected(request('sort', 'newest' )==='newest' )>Newest</option>
                    <option value="price_asc" @selected(request('sort')==='price_asc' )>Price: low to high</option>
                    <option value="price_desc" @selected(request('sort')==='price_desc' )>Price: high to low</option>
                    <option value="name" @selected(request('sort')==='name' )>Name (A-Z)</option>
                </select>

                <div class="flex gap-2 md:col-span-2">
                    <button class="btn-primary flex-1">Apply</button>
                    <a href="{{ route('catalog') }}" class="btn-outline flex-1">Reset</a>
                </div>
            </div>
        </form>

        {{-- Products --}}
        @if ($products->isEmpty())
        <x-ui.empty-state icon="flower" title="No flowers found" description="Try another search or reset the filters."
            class="mt-10">
            <a href="{{ route('catalog') }}" class="btn-primary">Reset filters</a>
        </x-ui.empty-state>
        @else
        <p class="mt-8 text-sm text-ink/60">
            Showing {{ $products->firstItem() }} to {{ $products->lastItem() }} of {{ $products->total() }} arrangements
        </p>

        <div class="mt-5 grid grid-cols-2 gap-x-4 gap-y-10 md:grid-cols-3 lg:grid-cols-4">
            @foreach ($products as $product)
            <div data-reveal style="--d: {{ ($loop->index % 4) * 70 }}ms">
                <x-product-card :product="$product" />
            </div>
            @endforeach
        </div>

        <div class="mt-12">{{ $products->links() }}</div>
        @endif
    </div>
</x-public-layout>
