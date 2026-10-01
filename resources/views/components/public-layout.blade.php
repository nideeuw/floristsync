@props(['title' => null])

@php
    $cartCount = app(\App\Services\CartService::class)->count();
    $whatsapp = config('floristsync.whatsapp_number');
    $navLinks = [
        ['label' => 'Home', 'route' => 'home', 'active' => 'home'],
        ['label' => 'Catalog', 'route' => 'catalog', 'active' => 'catalog'],
        ['label' => 'Track Order', 'route' => 'track', 'active' => 'track'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title . ' - ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col bg-cream font-sans text-ink antialiased">

    {{-- Header --}}
    <header x-data="{ open: false }" class="sticky top-0 z-30 border-b border-blush bg-cream/95 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3">
            <a href="{{ route('home') }}" class="flex items-center gap-2">
                <img src="{{ asset('images/logo.png') }}" alt="FloristSync logo"
                     class="h-10 w-10 object-contain mix-blend-multiply">
                <span class="text-xl font-bold tracking-tight text-moss">FloristSync</span>
            </a>

            <nav class="hidden items-center gap-8 text-sm font-medium md:flex">
                @foreach ($navLinks as $link)
                    <a href="{{ route($link['route']) }}"
                       class="{{ request()->routeIs($link['active'] . '*') ? 'font-semibold text-moss' : 'hover:text-moss' }}">
                        {{ $link['label'] }}
                    </a>
                @endforeach
            </nav>

            <div class="flex items-center gap-3 text-sm">
                {{-- Cart --}}
                <a href="{{ route('cart') }}" class="relative rounded-full p-2 hover:bg-blush" aria-label="Cart">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/>
                    </svg>
                    @if ($cartCount > 0)
                        <span class="absolute -right-0.5 -top-0.5 flex h-5 w-5 items-center justify-center rounded-full bg-moss text-xs font-bold text-white">
                            {{ $cartCount }}
                        </span>
                    @endif
                </a>

                {{-- Auth --}}
                @auth
                    @if (auth()->user()->hasRole('admin', 'owner', 'cashier'))
                        <a href="{{ route('dashboard') }}"
                           class="hidden rounded-full bg-blush px-3 py-1.5 font-medium hover:bg-petal sm:inline-block">Staff Panel</a>
                    @endif
                    <a href="{{ route('profile.edit') }}" class="hidden font-medium hover:text-moss sm:inline">
                        {{ auth()->user()->name }}
                    </a>
                    <form method="POST" action="{{ route('logout') }}" class="hidden sm:block">
                        @csrf
                        <button type="submit" class="hover:text-moss">Log out</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="hidden font-medium hover:text-moss sm:inline">Log in</a>
                    <a href="{{ route('register') }}" class="btn-primary hidden sm:inline-flex">Register</a>
                @endauth

                {{-- Mobile menu button --}}
                <button @click="open = !open" class="rounded p-2 text-xl hover:bg-blush md:hidden" aria-label="Menu">☰</button>
            </div>
        </div>

        {{-- Mobile menu --}}
        <div x-show="open" x-cloak class="space-y-2 border-t border-blush px-4 py-3 text-sm md:hidden">
            @foreach ($navLinks as $link)
                <a href="{{ route($link['route']) }}" class="block py-1 font-medium">{{ $link['label'] }}</a>
            @endforeach

            @auth
                @if (auth()->user()->hasRole('admin', 'owner', 'cashier'))
                    <a href="{{ route('dashboard') }}" class="block py-1 font-medium">Staff Panel</a>
                @endif
                <a href="{{ route('profile.edit') }}" class="block py-1 font-medium">Profile</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="py-1 font-medium">Log out</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="block py-1 font-medium">Log in</a>
                <a href="{{ route('register') }}" class="block py-1 font-medium">Register</a>
            @endauth
        </div>
    </header>

    {{-- Content --}}
    <main class="flex-1">
        @if (session('success') || session('error') || $errors->any())
            <div class="mx-auto max-w-7xl px-4 pt-4">
                @if (session('success'))
                    <div class="rounded-xl bg-sage/30 px-4 py-3 text-sm text-ink">{{ session('success') }}</div>
                @endif

                @if (session('error'))
                    <div class="rounded-xl bg-red-100 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
                @endif

                @if ($errors->any())
                    <div class="rounded-xl bg-red-100 px-4 py-3 text-sm text-red-800">
                        <ul class="list-inside list-disc">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endif

        {{ $slot }}
    </main>

    {{-- Footer --}}
    <footer class="mt-16 bg-ink text-cream">
        <div class="mx-auto grid max-w-7xl gap-8 px-4 py-10 md:grid-cols-3">
            <div>
                <div class="text-xl font-bold">FloristSync</div>
                <p class="mt-2 text-sm text-cream/70">Fresh flowers for every special moment. Order online or visit our store.</p>
            </div>
            <div class="text-sm">
                <div class="mb-2 font-semibold">Explore</div>
                <ul class="space-y-1 text-cream/70">
                    <li><a href="{{ route('catalog') }}" class="hover:text-white">Catalog</a></li>
                    <li><a href="{{ route('track') }}" class="hover:text-white">Track Order</a></li>
                    <li><a href="{{ route('cart') }}" class="hover:text-white">Cart</a></li>
                </ul>
            </div>
            <div class="text-sm">
                <div class="mb-2 font-semibold">Contact</div>
                <p class="text-cream/70">WhatsApp: +{{ $whatsapp }}</p>
                <p class="text-cream/70">Open daily, 08.00 - 20.00 WIB</p>
            </div>
        </div>
        <div class="border-t border-white/10 py-4 text-center text-xs text-cream/60">
            &copy; {{ date('Y') }} FloristSync. All rights reserved.
        </div>
    </footer>

    {{-- Floating WhatsApp button --}}
    <a href="https://wa.me/{{ $whatsapp }}?text={{ urlencode('Hello FloristSync, I would like to ask about your flowers.') }}"
       target="_blank" rel="noopener"
       class="fixed bottom-5 right-5 z-40 rounded-full bg-moss px-4 py-3 text-sm font-semibold text-white shadow-lg hover:bg-ink">
        💬 Chat on WhatsApp
    </a>
</body>
</html>
