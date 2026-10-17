@props([
    'code',
    'title',
    'message' => null,
    'icon' => 'alert-circle',
    'isExpired' => false,
])

@php
    $layout = match (true) {
        request()->is('admin', 'admin/*') => 'staff-layout',
        request()->is('pos', 'pos/*') => 'pos-layout',
        default => 'public-layout',
    };

    $user = auth()->user();

    $homeRoute = match (true) {
        request()->is('admin', 'admin/*') => $user ? route(app(\App\Services\AreaHomeService::class)->routeFor($user)) : route('admin.login'),
        request()->is('pos', 'pos/*') => $user ? route('cashier.pos') : route('cashier.login'),
        default => route('home'),
    };

    $homeLabel = match (true) {
        request()->is('admin', 'admin/*') => 'Admin Home',
        request()->is('pos', 'pos/*') => 'POS Terminal',
        default => 'Back to Home',
    };
@endphp

<x-dynamic-component :component="$layout" :title="$code . ' - ' . $title">
    <div @class([
        'flex items-center justify-center',
        'h-[calc(100vh-3.5rem)] overflow-y-auto p-4 sm:p-6' => request()->is('pos', 'pos/*'),
        'py-12 sm:py-20 px-4' => ! request()->is('pos', 'pos/*'),
    ])>
        <div class="relative mx-auto w-full max-w-lg text-center">
            {{-- Atmospheric Glow --}}
            <div class="pointer-events-none absolute -top-12 left-1/2 -translate-x-1/2 h-56 w-56 rounded-full bg-blush/40 blur-3xl" aria-hidden="true"></div>

            <div class="relative rounded-[2.5rem] bg-white/95 p-8 sm:p-12 shadow-xl ring-1 ring-blush/60 backdrop-blur-sm">
                {{-- Badge --}}
                <div class="inline-flex items-center gap-2 rounded-full bg-blush/50 px-4 py-1.5 text-xs font-semibold uppercase tracking-[0.2em] text-moss">
                    <x-ui.icon :name="$icon" class="h-4 w-4" />
                    <span>Error {{ $code }}</span>
                </div>

                {{-- Status Number --}}
                <div class="mt-4 font-display text-7xl sm:text-8xl font-semibold tracking-tight text-ink/80 select-none">
                    {{ $code }}
                </div>

                {{-- Title --}}
                <h1 class="mt-2 font-display text-3xl sm:text-4xl font-semibold text-ink">
                    {{ $title }}
                </h1>

                {{-- Message --}}
                <p class="mt-3 text-sm sm:text-base text-ink/70 leading-relaxed">
                    {{ $message }}
                </p>

                {{-- Action Buttons --}}
                <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                    @if ($isExpired)
                        <button type="button" onclick="window.location.reload()"
                            class="btn-primary">
                            <x-ui.icon name="refresh-cw" class="h-4 w-4" />
                            Refresh Page
                        </button>
                    @else
                        <a href="{{ $homeRoute }}" class="btn-primary">
                            <x-ui.icon name="arrow-left" class="h-4 w-4" />
                            {{ $homeLabel }}
                        </a>
                    @endif

                    <button type="button" onclick="if (window.history.length > 1) { window.history.back(); } else { window.location.href='{{ $homeRoute }}'; }"
                        class="btn-outline">
                        Go Back
                    </button>
                </div>

                @if (! request()->is('admin', 'admin/*', 'pos', 'pos/*'))
                    <div class="mt-8 pt-6 border-t border-blush/40 text-xs text-ink/60">
                        Need flowers right away? Explore our <a href="{{ route('catalog') }}" class="font-semibold text-moss underline hover:text-ink">flower catalog</a> or visit the <a href="{{ route('home') }}" class="font-semibold text-moss underline hover:text-ink">home page</a>.
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-dynamic-component>
