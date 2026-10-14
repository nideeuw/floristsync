@props(['title' => null])

@php
$cartCount = app(\App\Services\CartService::class)->count();
$storeSettings = app(\App\Services\SettingService::class);
$storeWhatsapp = $storeSettings->whatsappNumber();
$openingLines = $storeSettings->openingHoursSummary();
$navLinks = [
['label' => 'Home', 'route' => 'home', 'active' => 'home'],
['label' => 'Catalog', 'route' => 'catalog', 'active' => 'catalog'],
];
@endphp

<x-layouts.base :title="$title" class="flex min-h-screen flex-col">

    {{-- Header --}}
    <header x-data="{ mobileOpen: false, accountOpen: false }"
        class="sticky top-0 z-30 border-b border-blush bg-cream/95 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3">
            <x-ui.brand :href="route('home')" class="text-moss" />

            <nav class="hidden items-center gap-8 text-sm font-medium md:flex" aria-label="Main navigation">
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
                    <x-ui.icon name="shopping-cart" class="h-6 w-6" />
                    @if ($cartCount > 0)
                    <span class="absolute -right-0.5 -top-0.5 flex h-5 w-5 items-center justify-center rounded-full bg-moss text-xs font-bold text-white">
                        {{ $cartCount }}
                    </span>
                    @endif
                </a>

                {{-- Account (desktop) --}}
                @auth
                <div class="relative hidden md:block" @click.outside="accountOpen = false">
                    <button type="button" @click="accountOpen = !accountOpen" :aria-expanded="accountOpen"
                        class="flex items-center gap-2 rounded-full px-3 py-1.5 font-medium hover:bg-blush">
                        <x-ui.icon name="circle-user" class="h-5 w-5" />
                        <span class="max-w-32 truncate">{{ auth()->user()->name }}</span>
                        <x-ui.icon name="chevron-down" class="h-4 w-4" />
                    </button>

                    <div x-show="accountOpen" x-cloak
                        class="absolute right-0 mt-2 w-56 rounded-2xl border border-blush bg-white p-2 shadow-lg">
                        <x-nav.account-menu :links="$menuLinks" />
                    </div>
                </div>
                @else
                <div class="hidden items-center gap-3 md:flex">
                    <a href="{{ route('login') }}" class="font-medium hover:text-moss">Log in</a>
                    <a href="{{ route('register') }}" class="btn-primary">Register</a>
                </div>
                @endauth

                {{-- Mobile menu button --}}
                <button type="button" @click="mobileOpen = !mobileOpen" class="rounded p-2 hover:bg-blush md:hidden"
                    aria-label="Menu">
                    <x-ui.icon name="menu" class="h-6 w-6" />
                </button>
            </div>
        </div>

        {{-- Mobile menu --}}
        <div x-show="mobileOpen" x-cloak class="space-y-1 border-t border-blush px-4 py-3 text-sm md:hidden">
            @foreach ($navLinks as $link)
            <a href="{{ route($link['route']) }}"
                class="block rounded-xl px-3 py-2 font-medium hover:bg-blush">{{ $link['label'] }}</a>
            @endforeach

            <div class="my-2 border-t border-blush"></div>

            @auth
            <x-nav.account-menu :links="$menuLinks" />
            @else
            <a href="{{ route('login') }}" class="block rounded-xl px-3 py-2 font-medium hover:bg-blush">Log in</a>
            <a href="{{ route('register') }}" class="block rounded-xl px-3 py-2 font-medium hover:bg-blush">Register</a>
            @endauth
        </div>
    </header>

    {{-- Content --}}
    <main class="flex-1">
        <x-ui.flash class="mx-auto max-w-7xl px-4 pt-4" />

        {{ $slot }}
    </main>

    {{-- Footer --}}
    <footer class="mt-16 bg-ink text-cream">
        <div class="mx-auto grid max-w-7xl gap-8 px-4 py-10 md:grid-cols-3">
            <div>
                <div class="text-xl font-bold">{{ config('app.name') }}</div>
                <p class="mt-2 text-sm text-cream/70">Fresh flowers for every special moment. Order online or visit our store.</p>
            </div>
            <div class="text-sm">
                <div class="mb-2 font-semibold">Explore</div>
                <ul class="space-y-1 text-cream/70">
                    <li><a href="{{ route('catalog') }}" class="hover:text-white">Catalog</a></li>
                    <li><a href="{{ route('cart') }}" class="hover:text-white">Cart</a></li>
                </ul>
            </div>
            <div class="text-sm">
                <div class="mb-2 font-semibold">Contact</div>
                <p class="text-cream/70">WhatsApp: +{{ $storeWhatsapp }}</p>
                @foreach ($openingLines as $line)<p class="text-cream/70">{{ $line }}</p>@endforeach
            </div>
        </div>
        <div class="border-t border-white/10 py-4 text-center text-xs text-cream/60">
            &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
        </div>
    </footer>

    {{-- Floating WhatsApp button --}}
    <a href="{{ whatsapp_url('Hello ' . config('app.name') . ', I would like to ask about your flowers.') }}"
        target="_blank" rel="noopener"
        class="fixed bottom-5 right-5 z-40 flex items-center gap-2 rounded-full bg-moss px-4 py-3 text-sm font-semibold text-white shadow-lg hover:bg-ink">
        <x-ui.icon name="message-circle" class="h-5 w-5" />
        Chat on WhatsApp
    </a>
</x-layouts.base>
