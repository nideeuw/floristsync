@props(['product'])

<div class="card group flex flex-col overflow-hidden">
    <a href="{{ route('products.show', $product) }}">
        <x-product-image :product="$product" class="aspect-square transition group-hover:scale-105" />
    </a>

    <div class="flex flex-1 flex-col p-4">
        <span class="text-xs font-semibold uppercase tracking-wide text-moss">
            {{ $product->category?->category_name }}
        </span>

        <a href="{{ route('products.show', $product) }}" class="mt-1 font-semibold leading-snug hover:text-moss">
            {{ $product->product_name }}
        </a>

        <div class="mt-auto flex items-center justify-between pt-3">
            <span class="font-bold">{{ rupiah($product->price) }}</span>

            @if ($product->stock > 0)
                <form method="POST" action="{{ route('cart.add', $product) }}">
                    @csrf
                    <button class="btn-primary px-3 py-1.5 text-xs">Add</button>
                </form>
            @else
                <span class="rounded-full bg-gray-200 px-2 py-1 text-xs text-gray-600">Sold out</span>
            @endif
        </div>
    </div>
</div>
