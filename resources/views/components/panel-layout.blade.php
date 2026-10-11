@props([
    'title' => null,
    'subtitle' => null,
    'actions' => null,
    'menus',
    'user',
    'home',
    'logout',
    'brand' => null,
    'suffix' => null,
    'bell' => null,
    'fixed' => false,
])

@php
    $crumbs = [['label' => 'Home', 'href' => $home]];

    foreach ($menus as $menu) {
        if ($menu->menu_behavior === 'group' && $menu->active) {
            $crumbs[] = ['label' => $menu->menu_name, 'href' => null];
            $leaf = $menu->children->firstWhere('active', true);
            if ($leaf) {
                $crumbs[] = ['label' => $leaf->menu_name, 'href' => $leaf->href];
            }
        } elseif ($menu->menu_behavior !== 'group' && $menu->active) {
            $crumbs[] = ['label' => $menu->menu_name, 'href' => $menu->href];
        }
    }

    if ($title && end($crumbs)['label'] !== $title) {
        $crumbs[] = ['label' => $title, 'href' => null];
    }
    $crumbs[count($crumbs) - 1]['href'] = null;

    $searchItems = [];
    foreach ($menus as $menu) {
        $leaves = $menu->menu_behavior === 'group' ? $menu->children : collect([$menu]);
        foreach ($leaves as $leaf) {
            if ($leaf->href) {
                $searchItems[] = [
                    'label' => $leaf->menu_name,
                    'group' => $menu->menu_behavior === 'group' ? $menu->menu_name : 'Menu',
                    'href' => $leaf->href,
                ];
            }
        }
    }

    $brandName = $brand ?? config('app.name');
@endphp

<x-layouts.base :title="$title" :suffix="$suffix" :class="$fixed ? 'h-screen overflow-hidden' : 'min-h-screen'"
    x-data="{
        mobileOpen: false,
        collapsed: false,
        init() {
            try { this.collapsed = localStorage.getItem('fs.sidebar') === '1'; } catch (e) {}
            this.$watch('collapsed', (v) => { try { localStorage.setItem('fs.sidebar', v ? '1' : '0'); } catch (e) {} });
        },
        toggle() {
            if (window.matchMedia('(min-width: 768px)').matches) { this.collapsed = !this.collapsed; }
            else { this.mobileOpen = !this.mobileOpen; }
        }
    }"
    x-on:keydown.escape.window="mobileOpen = false">

    {{-- Mobile overlay --}}
    <div x-show="mobileOpen" x-cloak x-transition.opacity x-on:click="mobileOpen = false"
        class="fixed inset-0 z-40 bg-ink/50 md:hidden"></div>

    <div @class(['flex', 'h-screen overflow-hidden' => $fixed, 'min-h-screen' => ! $fixed])>

        {{-- Sidebar --}}
        <aside :class="[mobileOpen && '!translate-x-0', collapsed ? 'md:w-[4.5rem]' : 'md:w-64']"
            class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col bg-ink text-cream shadow-xl transition-[transform,width] duration-200 md:sticky md:top-3 md:m-3 md:h-[calc(100vh-1.5rem)] md:shrink-0 md:translate-x-0 md:rounded-2xl">

            <a href="{{ $home }}" class="flex h-14 shrink-0 items-center gap-3 px-4" title="{{ $brandName }}">
                <img src="{{ asset('images/logo.png') }}" alt=""
                    class="h-9 w-9 shrink-0 rounded-full bg-cream object-contain p-1">
                <span class="truncate text-lg font-bold" :class="collapsed && 'md:hidden'">{{ $brandName }}</span>
            </a>

            <x-nav.sidebar :menus="$menus" class="flex-1 overflow-y-auto px-3 py-2" />

            <div class="shrink-0 border-t border-white/10 p-3">
                <button type="button" x-on:click="mobileOpen = false"
                    class="flex w-full items-center justify-center gap-2 rounded-xl px-3 py-2 text-sm text-cream/80 hover:bg-white/10 md:hidden">
                    <x-ui.icon name="x" class="h-4 w-4" /> Close menu
                </button>
                <button type="button" x-on:click="collapsed = !collapsed"
                    class="hidden w-full items-center gap-3 rounded-xl px-3 py-2 text-sm text-cream/80 hover:bg-white/10 md:flex"
                    :title="collapsed ? 'Expand sidebar' : 'Collapse sidebar'">
                    <x-ui.icon name="panel-left-close" class="h-4 w-4 shrink-0" x-show="!collapsed" />
                    <x-ui.icon name="panel-left-open" class="h-4 w-4 shrink-0" x-show="collapsed" x-cloak />
                    <span :class="collapsed && 'md:hidden'">Collapse</span>
                </button>
            </div>
        </aside>

        {{-- Content column --}}
        <div @class(['flex min-w-0 flex-1 flex-col', 'h-screen' => $fixed])>

            {{-- Header --}}
            <header class="sticky top-0 z-30 flex h-14 shrink-0 items-center gap-3 border-b border-blush bg-white px-4 sm:px-6">
                <button type="button" x-on:click="toggle()" class="rounded-full p-2 hover:bg-cream" aria-label="Toggle sidebar">
                    <x-ui.icon name="menu" class="h-5 w-5" />
                </button>

                @if ($fixed)
                    <div class="min-w-0 flex-1 truncate font-semibold">{{ $title }}</div>

                    <div class="hidden text-right leading-tight sm:block"
                        x-data="{ now: new Date() }" x-init="setInterval(() => now = new Date(), 1000)">
                        <div class="font-mono text-sm font-semibold" x-text="now.toLocaleTimeString('en-GB')"></div>
                        <div class="text-xs text-ink/60"
                            x-text="now.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long' })"></div>
                    </div>
                @else
                    <x-nav.search :items="$searchItems" />
                    <div class="flex-1 sm:hidden"></div>
                @endif

                <div class="ml-auto flex items-center gap-1 sm:gap-2">
                    @if ($bell)
                        <x-nav.bell :href="$bell" :user="$user" />
                    @endif

                    <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false">
                        <button type="button" x-on:click="open = !open" :aria-expanded="open"
                            class="flex items-center gap-2 rounded-full p-1 sm:pr-3 hover:bg-cream">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-moss text-sm font-bold text-white">
                                {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                            </span>
                            <span class="hidden text-left leading-tight sm:block">
                                <span class="block max-w-32 truncate text-sm font-semibold">{{ $user->name }}</span>
                                <span class="block text-xs text-ink/60">{{ str((string) $user->role?->role_name)->headline() }}</span>
                            </span>
                            <x-ui.icon name="chevron-down" class="hidden h-4 w-4 sm:block" />
                        </button>

                        <div x-show="open" x-cloak
                            class="absolute right-0 mt-2 w-56 rounded-2xl border border-blush bg-white p-2 shadow-lg">
                            <div class="break-all px-3 py-2 text-xs text-ink/60">{{ $user->email }}</div>
                            <x-nav.logout :action="$logout" class="w-full gap-3 rounded-xl px-3 py-2 text-sm text-ink hover:bg-blush" />
                        </div>
                    </div>
                </div>
            </header>

            {{-- Page --}}
            @if ($fixed)
                <main class="min-h-0 flex-1 overflow-hidden">
                    {{ $slot }}
                </main>
            @else
                <main class="min-w-0 flex-1 p-4 sm:p-6">
                    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
                        <div class="min-w-0">
                            <nav aria-label="Breadcrumb" class="mb-1 flex flex-wrap items-center gap-1 text-xs text-ink/60">
                                @foreach ($crumbs as $crumb)
                                    @if (! $loop->first)
                                        <x-ui.icon name="chevron-right" class="h-3 w-3" />
                                    @endif
                                    @if ($crumb['href'])
                                        <a href="{{ $crumb['href'] }}" class="hover:text-moss">{{ $crumb['label'] }}</a>
                                    @else
                                        <span @class(['font-semibold text-ink' => $loop->last])>{{ $crumb['label'] }}</span>
                                    @endif
                                @endforeach
                            </nav>

                            @if ($title)
                                <h1 class="text-2xl font-bold">{{ $title }}</h1>
                            @endif
                            @if ($subtitle)
                                <p class="text-sm text-ink/60">{{ $subtitle }}</p>
                            @endif
                        </div>

                        @if ($actions && $actions->isNotEmpty())
                            <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
                        @endif
                    </div>

                    <x-ui.flash />

                    {{ $slot }}
                </main>
            @endif
        </div>
    </div>
</x-layouts.base>
