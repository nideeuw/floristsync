@php
$lowStock = (int) config('floristsync.low_stock_threshold', 5);
@endphp

<x-public-layout :title="$product->product_name">
    <div class="mx-auto max-w-7xl px-4 py-8 md:py-12">
        {{-- Breadcrumb --}}
        <nav class="flex flex-wrap items-center gap-2 text-sm text-ink/60" aria-label="Breadcrumb">
            <a href="{{ route('home') }}" class="transition hover:text-moss">Home</a>
            <x-ui.icon name="chevron-right" class="h-4 w-4" />
            <a href="{{ route('catalog') }}" class="transition hover:text-moss">Catalog</a>
            @if ($product->category)
            <x-ui.icon name="chevron-right" class="h-4 w-4" />
            <a href="{{ route('catalog', ['category' => $product->category_id]) }}"
                class="transition hover:text-moss">{{ $product->category->category_name }}</a>
            @endif
        </nav>

        <div class="mt-6 grid gap-8 lg:grid-cols-2 lg:gap-16">
            {{-- Image --}}
            <div class="relative">
                <div class="overflow-hidden rounded-[2.5rem] bg-cream shadow-sm ring-1 ring-blush/60">
                    <x-product-image :product="$product" class="aspect-square w-full" />
                </div>
                @if ($product->stock < 1)
                <span class="absolute left-5 top-5 rounded-full bg-ink px-4 py-1.5 text-xs font-semibold text-cream">Sold out</span>
                @endif
            </div>

            {{-- Info --}}
            <div class="lg:py-6">
                @if ($product->category)
                <span class="eyebrow">{{ $product->category->category_name }}</span>
                @endif
                <h1 class="mt-3 font-display text-4xl font-semibold leading-tight md:text-6xl">{{ $product->product_name }}</h1>
                <p class="mt-4 text-3xl font-bold text-moss">{{ rupiah($product->price) }}</p>

                <div class="mt-5">
                    @if ($product->stock > $lowStock)
                    <span class="inline-flex items-center gap-2 rounded-full bg-sage/30 px-4 py-1.5 text-sm font-semibold text-ink">
                        <span class="h-2 w-2 rounded-full bg-moss"></span> In stock ({{ $product->stock }} available)
                    </span>
                    @elseif ($product->stock > 0)
                    <span class="inline-flex items-center gap-2 rounded-full bg-orange-100 px-4 py-1.5 text-sm font-semibold text-orange-700">
                        <span class="h-2 w-2 rounded-full bg-orange-500"></span> Only {{ $product->stock }} left
                    </span>
                    @else
                    <span class="inline-flex items-center gap-2 rounded-full bg-red-100 px-4 py-1.5 text-sm font-semibold text-red-700">
                        <span class="h-2 w-2 rounded-full bg-red-500"></span> Sold out
                    </span>
                    @endif
                </div>

                @if ($product->description)
                <p class="mt-6 max-w-xl leading-relaxed text-ink/70">{{ $product->description }}</p>
                @endif

                @if ($product->stock > 0)
                <form method="POST" action="{{ route('cart.add', $product) }}"
                    x-data="{ qty: 1, max: {{ (int) $product->stock }} }"
                    class="mt-8 flex flex-wrap items-center gap-3">
                    @csrf
                    <div class="flex h-12 items-center rounded-full border border-blush bg-white">
                        <button type="button" @click="qty = Math.max(1, qty - 1)" aria-label="Decrease quantity"
                            class="flex h-12 w-12 items-center justify-center rounded-full transition hover:bg-blush/50">
                            <x-ui.icon name="minus" class="h-4 w-4" />
                        </button>
                        <input type="number" name="qty" x-model.number="qty" min="1" max="{{ $product->stock }}"
                            class="w-12 appearance-none border-0 bg-transparent p-0 text-center text-sm font-semibold focus:ring-0 [-moz-appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none">
                        <button type="button" @click="qty = Math.min(max, qty + 1)" aria-label="Increase quantity"
                            class="flex h-12 w-12 items-center justify-center rounded-full transition hover:bg-blush/50">
                            <x-ui.icon name="plus" class="h-4 w-4" />
                        </button>
                    </div>
                    <button class="btn-primary h-12 flex-1 px-8 text-base sm:flex-none">
                        <x-ui.icon name="shopping-cart" class="h-5 w-5" />
                        Add to cart
                    </button>
                </form>
                @endif

                <a href="{{ whatsapp_url('Hello ' . config('app.name') . ', I am interested in ' . $product->product_name) }}"
                    target="_blank" rel="noopener" class="btn-outline mt-4">
                    <x-ui.icon name="message-circle" class="h-4 w-4" />
                    Ask via WhatsApp
                </a>

                <div class="mt-10 grid gap-4 border-t border-blush/80 pt-8 sm:grid-cols-2">
                    <div class="flex items-start gap-3 text-sm">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-blush/60 text-moss">
                            <x-ui.icon name="leaf" class="h-5 w-5" />
                        </span>
                        <span><span class="block font-semibold">Fresh every day</span><span class="text-ink/60">Arranged by our florists.</span></span>
                    </div>
                    <div class="flex items-start gap-3 text-sm">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-blush/60 text-moss">
                            <x-ui.icon name="truck" class="h-5 w-5" />
                        </span>
                        <span><span class="block font-semibold">Pickup or delivery</span><span class="text-ink/60">Chosen at checkout.</span></span>
                    </div>
                </div>
            </div>
        </div>

        @if ($related->isNotEmpty())
        <section class="mt-20 md:mt-28">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <span class="eyebrow">More to love</span>
                    <h2 class="section-title mt-2">You may also like</h2>
                </div>
                <a href="{{ route('catalog') }}" class="btn-outline">
                    View all
                    <x-ui.icon name="arrow-right" class="h-4 w-4" />
                </a>
            </div>
            <div class="mt-8 grid grid-cols-2 gap-x-4 gap-y-10 md:grid-cols-4">
                @foreach ($related as $item)
                <x-product-card :product="$item" />
                @endforeach
            </div>
        </section>
        @endif
    </div>
</x-public-layout>
