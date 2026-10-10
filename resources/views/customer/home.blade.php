@php
$categoryIcons = ['flower-2', 'flower', 'sprout', 'leaf', 'heart', 'gift'];
$benefits = [
['icon' => 'leaf', 'title' => 'Fresh Flowers', 'text' => 'Arranged fresh and carefully by our florists every day.'],
['icon' => 'truck', 'title' => 'Pickup or Delivery', 'text' => 'Pick up at our store or get it delivered by courier.'],
['icon' => 'shield-check', 'title' => 'Secure Payment', 'text' => 'Pay with QRIS, Virtual Account, or e-wallet.'],
];
@endphp

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
                    @guest
                    <a href="{{ route('register') }}" class="btn-outline">Create Account</a>
                    @endguest
                </div>
            </div>

            <div class="flex justify-center">
                <div class="flex h-72 w-72 items-center justify-center rounded-full bg-white shadow-lg ring-8 ring-petal/40">
                    <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }}" class="h-52 object-contain">
                </div>
            </div>
        </div>
    </section>

    {{-- Categories --}}
    <section class="mx-auto max-w-7xl px-4 py-14">
        <h2 class="text-2xl font-bold">Shop by Category</h2>

        <div class="mt-6 grid grid-cols-2 gap-4 md:grid-cols-5">
            @foreach ($categories as $category)
            <a href="{{ route('catalog', ['category' => $category->id]) }}"
                class="card p-5 text-center transition hover:border-petal hover:shadow-md">
                <x-ui.icon :name="$categoryIcons[$loop->index % count($categoryIcons)]" class="mx-auto h-9 w-9 text-moss" />
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
            @foreach ($benefits as $benefit)
            <div class="text-center">
                <x-ui.icon :name="$benefit['icon']" class="mx-auto h-9 w-9 text-moss" />
                <h3 class="mt-2 font-semibold">{{ $benefit['title'] }}</h3>
                <p class="mt-1 text-sm text-gray-600">{{ $benefit['text'] }}</p>
            </div>
            @endforeach
        </div>
    </section>

    {{-- CTA --}}
    <section class="bg-moss">
        <div class="mx-auto max-w-7xl px-4 py-12 text-center text-white">
            <h2 class="text-2xl font-bold">Need a custom arrangement?</h2>
            <p class="mt-2 text-white/90">Tell us your idea and we will make it happen.</p>
            <a href="{{ whatsapp_url() }}" target="_blank" rel="noopener" class="btn-secondary mt-5">Chat on WhatsApp</a>
        </div>
    </section>
</x-public-layout>
