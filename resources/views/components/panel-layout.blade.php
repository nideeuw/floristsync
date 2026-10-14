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
        desktop: window.matchMedia('(min-width: 1024px)').matches,
        collapsed: document.documentElement.classList.contains('fs-collapsed'),
        init() {
            const mq = window.matchMedia('(min-width: 1024px)');
            mq.addEventListener('change', (e) => {
                this.desktop = e.matches;
                if (e.matches) { this.mobileOpen = false; }
            });
            this.$watch('collapsed', (v) => {
                document.documentElement.classList.toggle('fs-collapsed', v);
                try { localStorage.setItem('fs.sidebar', v ? '1' : '0'); } catch (e) {}
            });
            this.$watch('mobileOpen', (v) => document.documentElement.classList.toggle('overflow-hidden', v));
            requestAnimationFrame(() => requestAnimationFrame(() => document.documentElement.classList.add('fs-ready')));
        },
        toggle() {
            if (this.desktop) { this.collapsed = !this.collapsed; } else { this.mobileOpen = !this.mobileOpen; }
        }
    }"
    x-on:keydown.escape.window="mobileOpen = false">

    {{-- Restore the desktop sidebar state before the first paint (no flash) --}}
    <script>
        try { if (localStorage.getItem('fs.sidebar') === '1') { document.documentElement.classList.add('fs-collapsed'); } } catch (e) {}
    </script>

    {{-- Mobile overlay --}}
    <div x-show="mobileOpen" x-cloak x-transition.opacity x-on:click="mobileOpen = false"
        class="fixed inset-0 z-40 bg-ink/40 lg:hidden" aria-hidden="true"></div>

    <div @class(['flex bg-white', 'h-screen overflow-hidden' => $fixed, 'min-h-screen' => ! $fixed])>

        {{-- Sidebar --}}
        <aside id="app-sidebar" aria-label="Sidebar"
            :class="mobileOpen && '!translate-x-0'"
            x-effect="$el.toggleAttribute('inert', !desktop && !mobileOpen)"
            x-on:click="if ($event.target.closest('a')) { mobileOpen = false }"
            class="fs-shell-aside fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col overflow-hidden border-r border-blush bg-white shadow-xl lg:sticky lg:top-0 lg:z-30 lg:h-screen lg:shrink-0 lg:translate-x-0 lg:shadow-none">

            <div class="fs-center-collapsed flex h-14 shrink-0 items-center justify-between border-b border-blush/70 px-5">
                <x-ui.brand :href="$home" :name="$brandName" text-class="fs-hide-collapsed"
                    class="min-w-0 text-moss" :title="$brandName" />

                <button type="button" x-on:click="mobileOpen = false" aria-label="Close menu"
                    class="rounded-xl p-2 text-ink/60 transition-colors hover:bg-cream/60 hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sage lg:hidden">
                    <x-ui.icon name="x" class="h-5 w-5" />
                </button>
            </div>

            <x-nav.sidebar :menus="$menus" class="min-h-0 flex-1 overflow-y-auto overflow-x-hidden px-3 py-4" />
        </aside>

        {{-- Content column --}}
        <div @class(['flex min-w-0 flex-1 flex-col bg-cream/30', 'h-screen' => $fixed])>

            {{-- Header --}}
            <header class="sticky top-0 z-20 flex h-14 shrink-0 items-center gap-3 border-b border-blush/70 bg-white px-4 sm:px-6">
                <button type="button" x-on:click="toggle()" aria-controls="app-sidebar" aria-label="Toggle sidebar"
                    :aria-expanded="(desktop ? !collapsed : mobileOpen) ? 'true' : 'false'"
                    class="rounded-xl p-2 text-ink/70 transition-colors hover:bg-cream/60 hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sage">
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

                    <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false"
                        x-on:keydown.escape="open = false">
                        <button type="button" x-on:click="open = !open" :aria-expanded="open" aria-haspopup="menu"
                            class="flex items-center gap-2 rounded-xl p-1 transition-colors hover:bg-cream/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sage sm:pr-3">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-moss text-sm font-bold text-white">
                                {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                            </span>
                            <span class="hidden text-left leading-tight sm:block">
                                <span class="block max-w-32 truncate text-sm font-semibold">{{ $user->name }}</span>
                                <span class="block text-xs text-ink/60">{{ str((string) $user->role?->role_name)->headline() }}</span>
                            </span>
                            <x-ui.icon name="chevron-down" class="hidden h-4 w-4 text-ink/50 sm:block" />
                        </button>

                        <div x-show="open" x-cloak x-transition.origin.top.right
                            class="absolute right-0 z-50 mt-2 w-60 rounded-2xl border border-blush bg-white p-2 shadow-lg">
                            <div class="px-3 py-2">
                                <div class="truncate text-sm font-semibold">{{ $user->name }}</div>
                                <div class="break-all text-xs text-ink/60">{{ $user->email }}</div>
                            </div>
                            <div class="my-1 border-t border-blush/70"></div>
                            <x-nav.logout :action="$logout" class="w-full gap-3 rounded-xl px-3 py-2 text-sm text-ink transition-colors hover:bg-blush/50" />
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
                <main class="min-w-0 flex-1 p-4 sm:p-6 lg:p-8">
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
