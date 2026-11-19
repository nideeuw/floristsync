@props([
    'code',
    'title',
    'message' => null,
    'icon' => 'alert-circle',
    'isExpired' => false,
])

@php
    $user = auth()->user();
    $inAdmin = request()->is('admin', 'admin/*');
    $inPos = request()->is('pos', 'pos/*');
    $isStaff = $user && $user->hasRole(...\App\Models\Role::ADMIN_PANEL_ROLES);
    $isCashier = $user && $user->hasRole(...\App\Models\Role::POS_ROLES);

    // Staff screens are only drawn for people who belong there.
    // Everyone else (members, guests) gets a plain page that shows nothing of the panel.
    $layout = match (true) {
        $inAdmin && $isStaff => 'staff-layout',
        $inPos && $isCashier => 'pos-layout',
        $inAdmin || $inPos => 'bare-layout',
        default => 'public-layout',
    };

    [$homeRoute, $homeLabel] = match (true) {
        $isStaff && ($inAdmin || $layout === 'bare-layout') => [route(app(\App\Services\AreaHomeService::class)->routeFor($user)), 'Admin Home'],
        $isCashier && ($inPos || $layout === 'bare-layout') => [route('cashier.pos'), 'POS Terminal'],
        $layout === 'bare-layout' => [route('home'), 'Back to shop'],
        default => [route('home'), 'Back to Home'],
    };
@endphp

<x-dynamic-component :component="$layout" :title="$code . ' - ' . $title">
    <div @class([
        'flex items-center justify-center',
        'h-[calc(100vh-3.5rem)] overflow-y-auto p-4 sm:p-6' => $layout === 'pos-layout',
        'px-4 py-8' => $layout === 'bare-layout',
        'px-4 py-12 sm:py-20' => ! in_array($layout, ['pos-layout', 'bare-layout']),
    ])>
        <div class="relative mx-auto w-full max-w-lg text-center">
            {{-- Atmospheric Glow --}}
            <div class="pointer-events-none absolute -top-12 left-1/2 h-56 w-56 -translate-x-1/2 rounded-full bg-blush/40 blur-3xl" aria-hidden="true"></div>

            <div class="relative rounded-[2.5rem] bg-white/95 p-8 shadow-xl ring-1 ring-blush/60 backdrop-blur-sm sm:p-12">
                {{-- Icon --}}
                <div class="mx-auto flex h-14 w-14 animate-float items-center justify-center rounded-full bg-blush/60 text-moss">
                    <x-ui.icon :name="$icon" class="h-7 w-7" />
                </div>

                {{-- Status Number --}}
                <div class="mt-5 animate-fade-up select-none font-display text-7xl font-semibold tracking-tight text-ink/80 sm:text-8xl">
                    {{ $code }}
                </div>

                {{-- Title --}}
                <h1 class="mt-2 font-display text-3xl font-semibold text-ink sm:text-4xl">
                    {{ $title }}
                </h1>

                {{-- Message --}}
                <p class="mt-3 text-sm leading-relaxed text-ink/70 sm:text-base">
                    {{ $message }}
                </p>

                {{-- Action Buttons --}}
                <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                    @if ($isExpired)
                        <button type="button" onclick="window.location.reload()" class="btn-primary">
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

                @if ($layout === 'public-layout')
                    <div class="mt-8 border-t border-blush/40 pt-6 text-xs text-ink/60">
                        Need flowers right away? Explore our <a href="{{ route('catalog') }}" class="font-semibold text-moss underline hover:text-ink">flower catalog</a> or visit the <a href="{{ route('home') }}" class="font-semibold text-moss underline hover:text-ink">home page</a>.
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-dynamic-component>