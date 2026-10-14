@props(['product'])

@php
$lowStock = (int) config('floristsync.low_stock_threshold', 5);
@endphp

<article class="group flex flex-col">
    <div class="relative overflow-hidden rounded-3xl bg-cream ring-1 ring-blush/60 transition duration-500 group-hover:-translate-y-1 group-hover:shadow-xl">
        <a href="{{ route('products.show', $product) }}" class="block" aria-label="{{ $product->product_name }}">
            <x-product-image :product="$product"
                class="aspect-[4/5] w-full transition duration-700 ease-out group-hover:scale-105" />
        </a>

        @if ($product->category)
        <span class="pointer-events-none absolute left-3 top-3 rounded-full bg-white/90 px-3 py-1 text-[11px] font-semibold uppercase tracking-wider text-moss backdrop-blur">
            {{ $product->category->category_name }}
        </span>
        @endif

        @if ($product->stock > 0 && $product->stock <= $lowStock)
        <span class="pointer-events-none absolute bottom-3 left-3 rounded-full bg-ink/80 px-3 py-1 text-[11px] font-semibold text-cream backdrop-blur">
            Only {{ $product->stock }} left
        </span>
        @endif

        @if ($product->stock < 1)
        <div class="pointer-events-none absolute inset-0 flex items-center justify-center bg-white/60 backdrop-blur-[1px]">
            <span class="rounded-full bg-ink px-4 py-1.5 text-xs font-semibold text-cream">Sold out</span>
        </div>
        @endif
    </div>

    <div class="mt-4 flex flex-1 items-start justify-between gap-3 px-1">
        <div class="min-w-0">
            <a href="{{ route('products.show', $product) }}"
                class="block font-display text-xl font-semibold leading-snug transition hover:text-moss md:text-2xl">
                {{ $product->product_name }}
            </a>
            <p class="mt-1 text-sm font-semibold text-moss">{{ rupiah($product->price) }}</p>
        </div>

        @if ($product->stock > 0)
        <form method="POST" action="{{ route('cart.add', $product) }}" class="shrink-0">
            @csrf
            <button type="submit" aria-label="Add {{ $product->product_name }} to cart"
                class="flex h-11 w-11 items-center justify-center rounded-full bg-moss text-white shadow-md transition hover:scale-105 hover:bg-ink">
                <x-ui.icon name="plus" class="h-5 w-5" />
            </button>
        </form>
        @endif
    </div>
</article>
