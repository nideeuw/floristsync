@props(['title' => null])

@php
$cartCount = app(\App\Services\CartService::class)->count();
$storeSettings = app(\App\Services\SettingService::class);
$storeWhatsapp = $storeSettings->whatsappNumber();
$openingLines = $storeSettings->openingHoursSummary();
$navLinks = [
['label' => 'Home', 'href' => route('home'), 'active' => request()->routeIs('home')],
['label' => 'Catalog', 'href' => route('catalog'), 'active' => request()->routeIs('catalog*', 'products.*')],
['label' => 'Contact', 'href' => '#contact', 'active' => false],
];
@endphp

<x-layouts.base :title="$title" class="flex min-h-screen flex-col bg-paper text-ink">

    {{-- Header --}}
    <header x-data="{ mobileOpen: false, accountOpen: false, scrolled: false }"
        x-init="scrolled = window.scrollY > 12"
        @scroll.window.passive="scrolled = window.scrollY > 12"
        :class="scrolled ? 'bg-white/85 shadow-[0_10px_30px_-14px_rgba(55,64,48,0.35)] backdrop-blur-xl' : 'bg-paper/90 backdrop-blur'"
        class="sticky top-0 z-40 transition-all duration-300">
        <div :class="scrolled ? 'py-2.5' : 'py-4'"
            class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 transition-all duration-300">

            <div class="flex flex-1 items-center">
                <x-brand-mark :href="route('home')" />
            </div>

            <nav class="hidden items-center gap-1 text-sm font-medium md:flex" aria-label="Main navigation">
                @foreach ($navLinks as $link)
                <a href="{{ $link['href'] }}"
                    class="relative rounded-full px-4 py-2 transition {{ $link['active'] ? 'bg-blush/60 font-semibold text-moss' : 'text-ink/80 hover:bg-blush/40 hover:text-moss' }}">
                    {{ $link['label'] }}
                </a>
                @endforeach
            </nav>

            <div class="flex flex-1 items-center justify-end gap-2 text-sm">
                {{-- Cart --}}
                <a href="{{ route('cart') }}"
                    class="relative flex h-11 w-11 items-center justify-center rounded-full border border-blush bg-white/70 transition hover:bg-blush"
                    aria-label="Cart">
                    <x-ui.icon name="shopping-cart" class="h-5 w-5" />
                    @if ($cartCount > 0)
                    <span class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-moss px-1 text-[11px] font-bold text-white">
                        {{ $cartCount }}
                    </span>
                    @endif
                </a>

                {{-- Account (desktop) --}}
                @auth
                <div class="relative hidden md:block" @click.outside="accountOpen = false">
                    <button type="button" @click="accountOpen = !accountOpen" :aria-expanded="accountOpen"
                        class="flex h-11 items-center gap-2 rounded-full border border-blush bg-white/70 px-4 font-medium transition hover:bg-blush">
                        <x-ui.icon name="circle-user" class="h-5 w-5" />
                        <span class="max-w-32 truncate">{{ auth()->user()->name }}</span>
                        <x-ui.icon name="chevron-down" class="h-4 w-4" />
                    </button>

                    <div x-show="accountOpen" x-cloak
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 -translate-y-1"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        class="absolute right-0 mt-3 w-60 rounded-3xl border border-blush bg-white p-2 shadow-xl">
                        <x-nav.account-menu :links="$menuLinks" />
                    </div>
                </div>
                @else
                <div class="hidden items-center gap-2 md:flex">
                    <a href="{{ route('login') }}" class="rounded-full px-4 py-2.5 font-medium transition hover:bg-blush/50 hover:text-moss">Log in</a>
                    <a href="{{ route('register') }}" class="btn-primary">Register</a>
                </div>
                @endauth

                {{-- Mobile menu button --}}
                <button type="button" @click="mobileOpen = !mobileOpen" :aria-expanded="mobileOpen"
                    class="flex h-11 w-11 items-center justify-center rounded-full border border-blush bg-white/70 transition hover:bg-blush md:hidden"
                    aria-label="Menu">
                    <x-ui.icon name="menu" class="h-5 w-5" />
                </button>
            </div>
        </div>

        {{-- Mobile menu --}}
        <div x-show="mobileOpen" x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            class="mx-3 mb-3 space-y-1 rounded-3xl border border-blush bg-white p-3 text-sm shadow-xl md:hidden">
            @foreach ($navLinks as $link)
            <a href="{{ $link['href'] }}" @click="mobileOpen = false"
                class="block rounded-2xl px-4 py-3 font-medium hover:bg-blush/50">{{ $link['label'] }}</a>
            @endforeach

            <div class="my-2 border-t border-blush"></div>

            @auth
            <x-nav.account-menu :links="$menuLinks" />
            @else
            <a href="{{ route('login') }}" class="block rounded-2xl px-4 py-3 font-medium hover:bg-blush/50">Log in</a>
            <a href="{{ route('register') }}" class="btn-primary mt-1 w-full">Register</a>
            @endauth
        </div>
    </header>

    {{-- Content --}}
    <main class="flex-1">
        <x-ui.flash class="mx-auto max-w-7xl px-4 pt-4" />

        {{ $slot }}
    </main>

    {{-- Footer --}}
    <footer id="contact" class="relative mt-24 overflow-hidden rounded-t-[2.5rem] bg-ink text-cream">
        <div class="mx-auto grid max-w-7xl gap-10 px-6 pb-10 pt-14 md:grid-cols-12">
            <div class="md:col-span-5">
                <div class="font-display text-3xl font-semibold">{{ config('app.name') }}</div>
                <p class="mt-3 max-w-sm text-sm leading-relaxed text-cream/70">
                    Fresh flowers for every special moment. Order online for pickup or delivery, or visit our store.
                </p>
                <a href="{{ whatsapp_url('Hello ' . config('app.name') . ', I would like to ask about your flowers.') }}"
                    target="_blank" rel="noopener" class="btn-light mt-6">
                    <x-ui.icon name="message-circle" class="h-4 w-4" />
                    Chat on WhatsApp
                </a>
            </div>

            <div class="text-sm md:col-span-3">
                <div class="mb-3 text-xs font-semibold uppercase tracking-[0.22em] text-petal">Explore</div>
                <ul class="space-y-2 text-cream/70">
                    <li><a href="{{ route('home') }}" class="transition hover:text-white">Home</a></li>
                    <li><a href="{{ route('catalog') }}" class="transition hover:text-white">Catalog</a></li>
                    <li><a href="{{ route('cart') }}" class="transition hover:text-white">Cart</a></li>
                    @guest
                    <li><a href="{{ route('login') }}" class="transition hover:text-white">Log in</a></li>
                    <li><a href="{{ route('register') }}" class="transition hover:text-white">Create account</a></li>
                    @endguest
                </ul>
            </div>

            <div class="text-sm md:col-span-4">
                <div class="mb-3 text-xs font-semibold uppercase tracking-[0.22em] text-petal">Visit and contact</div>
                <p class="text-cream/70">WhatsApp: +{{ $storeWhatsapp }}</p>
                <div class="mt-2 space-y-1">
                    @foreach ($openingLines as $line)<p class="text-cream/70">{{ $line }}</p>@endforeach
                </div>
                <p class="mt-4 text-xs text-cream/50">We accept card, QRIS and e-wallet payments.</p>
            </div>
        </div>

        <div aria-hidden="true"
            class="pointer-events-none select-none whitespace-nowrap px-6 text-center font-display text-[22vw] leading-[0.8] text-white/5 md:text-[11rem]">
            {{ config('app.name') }}
        </div>

        <div class="border-t border-white/10 py-5 text-center text-xs text-cream/60">
            &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
        </div>
    </footer>

    {{-- Floating WhatsApp button --}}
    <a href="{{ whatsapp_url('Hello ' . config('app.name') . ', I would like to ask about your flowers.') }}"
        target="_blank" rel="noopener" aria-label="Chat on WhatsApp"
        class="group fixed bottom-5 right-5 z-40 flex items-center gap-2 rounded-full bg-moss p-3.5 text-sm font-semibold text-white shadow-xl transition hover:bg-ink md:px-5">
        <x-ui.icon name="message-circle" class="h-5 w-5" />
        <span class="hidden md:inline">Chat on WhatsApp</span>
    </a>
</x-layouts.base>
