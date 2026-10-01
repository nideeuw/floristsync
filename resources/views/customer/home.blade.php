<x-public-layout>
    {{-- Hero --}}
    <section class="bg-blush/50">
        <div class="mx-auto grid max-w-7xl items-center gap-10 px-4 py-16 md:grid-cols-2 md:py-24">
            <div>
                <span class="inline-block rounded-full bg-petal/40 px-3 py-1 text-xs font-semibold uppercase tracking-widest text-moss">
                    Fresh every day
                </span>
                <h1 class="mt-4 text-4xl font-extrabold leading-tight md:text-5xl">
                    Beautiful flowers for every <span class="text-moss">special moment</span>
                </h1>
                <p class="mt-4 max-w-md text-gray-700">
                    Bouquets, flower boxes, and standing flowers, ready to pick up in store or delivered to your door.
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('catalog') }}" class="btn-primary">Shop Now</a>
                    <a href="{{ route('track') }}" class="btn-outline">Track Order</a>
                </div>
            </div>

            <div class="flex justify-center">
                <div class="flex h-72 w-72 items-center justify-center rounded-full bg-white shadow-lg ring-8 ring-petal/40">
                    <img src="{{ asset('images/logo.png') }}" alt="FloristSync" class="h-52 object-contain">
                </div>
            </div>
        </div>
    </section>

    {{-- Categories --}}
    <section class="mx-auto max-w-7xl px-4 py-14">
        <h2 class="text-2xl font-bold">Shop by Category</h2>

        <div class="mt-6 grid grid-cols-2 gap-4 md:grid-cols-5">
            @php($icons = ['🌹', '🌷', '💐', '🌸', '🌼', '🌺'])

            @foreach ($categories as $category)
                <a href="{{ route('catalog', ['category' => $category->id]) }}"
                   class="card p-5 text-center transition hover:border-petal hover:shadow-md">
                    <div class="text-4xl">{{ $icons[$loop->index % 6] }}</div>
                    <div class="mt-2 font-semibold">{{ $category->category_name }}</div>
                    <div class="text-xs text-gray-500">{{ $category->products_count }} items</div>
                </a>
            @endforeach
        </div>
    </section>

    {{-- Featured products --}}
    <section class="mx-auto max-w-7xl px-4 pb-14">
        <div class="flex items-end justify-between">
            <h2 class="text-2xl font-bold">Latest Arrangements</h2>
            <a href="{{ route('catalog') }}" class="text-sm font-semibold text-moss hover:underline">View all &rarr;</a>
        </div>

        <div class="mt-6 grid grid-cols-2 gap-4 md:grid-cols-4">
            @foreach ($featured as $product)
                <x-product-card :product="$product" />
            @endforeach
        </div>
    </section>

    {{-- Benefits --}}
    <section class="bg-white">
        <div class="mx-auto grid max-w-7xl gap-6 px-4 py-14 md:grid-cols-3">
            <div class="text-center">
                <div class="text-4xl">🌿</div>
                <h3 class="mt-2 font-semibold">Fresh Flowers</h3>
                <p class="mt-1 text-sm text-gray-600">Arranged fresh and carefully by our florists every day.</p>
            </div>
            <div class="text-center">
                <div class="text-4xl">🚚</div>
                <h3 class="mt-2 font-semibold">Pickup or Delivery</h3>
                <p class="mt-1 text-sm text-gray-600">Pick up at our store or get it delivered by courier.</p>
            </div>
            <div class="text-center">
                <div class="text-4xl">🔒</div>
                <h3 class="mt-2 font-semibold">Secure Payment</h3>
                <p class="mt-1 text-sm text-gray-600">Pay with QRIS, Virtual Account, or e-wallet.</p>
            </div>
        </div>
    </section>

    {{-- CTA --}}
    <section class="bg-moss">
        <div class="mx-auto max-w-7xl px-4 py-12 text-center text-white">
            <h2 class="text-2xl font-bold">Need a custom arrangement?</h2>
            <p class="mt-2 text-white/90">Tell us your idea and we will make it happen.</p>
            <a href="https://wa.me/{{ config('floristsync.whatsapp_number') }}" target="_blank" rel="noopener"
               class="btn-secondary mt-5">Chat on WhatsApp</a>
        </div>
    </section>
</x-public-layout>
