<x-public-layout :title="$product->product_name">
    <div class="mx-auto max-w-7xl px-4 py-10">
        <a href="{{ route('catalog') }}" class="text-sm font-medium text-moss hover:underline">&larr; Back to catalog</a>

        <div class="mt-4 grid gap-10 md:grid-cols-2">
            <x-product-image :product="$product" class="aspect-square rounded-3xl" />

            <div>
                <span class="text-xs font-semibold uppercase tracking-widest text-moss">
                    {{ $product->category?->category_name }}
                </span>
                <h1 class="mt-1 text-3xl font-bold">{{ $product->product_name }}</h1>
                <p class="mt-3 text-2xl font-extrabold">{{ rupiah($product->price) }}</p>
                <p class="mt-4 text-gray-700">{{ $product->description }}</p>

                <p class="mt-4 text-sm font-medium">
                    @if ($product->stock > 5)
                        <span class="text-moss">In stock ({{ $product->stock }} available)</span>
                    @elseif ($product->stock > 0)
                        <span class="text-orange-600">Only {{ $product->stock }} left</span>
                    @else
                        <span class="text-red-600">Sold out</span>
                    @endif
                </p>

                @if ($product->stock > 0)
                    <form method="POST" action="{{ route('cart.add', $product) }}" class="mt-6 flex items-center gap-3">
                        @csrf
                        <input type="number" name="qty" value="1" min="1" max="{{ $product->stock }}" class="input w-24">
                        <button class="btn-primary">Add to Cart</button>
                    </form>
                @endif

                <a href="https://wa.me/{{ config('floristsync.whatsapp_number') }}?text={{ urlencode('Hello FloristSync, I am interested in ' . $product->product_name) }}"
                   target="_blank" rel="noopener" class="btn-outline mt-4">Ask via WhatsApp</a>
            </div>
        </div>

        @if ($related->isNotEmpty())
            <h2 class="mt-16 text-2xl font-bold">You may also like</h2>
            <div class="mt-6 grid grid-cols-2 gap-4 md:grid-cols-4">
                @foreach ($related as $item)
                    <x-product-card :product="$item" />
                @endforeach
            </div>
        @endif
    </div>
</x-public-layout>
