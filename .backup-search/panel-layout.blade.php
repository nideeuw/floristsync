@props([
    'title' => null,
    'subtitle' => null,
    'actions' => null,
    'menus' => collect(),
    'user' => null,
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
        tip: null,
        showTip(el, label) {
            if (! this.desktop || ! this.collapsed) { return; }
            const r = el.getBoundingClientRect();
            this.tip = { label, top: r.top + r.height / 2, left: r.right + 12 };
        },
        init() {
            const mq = window.matchMedia('(min-width: 1024px)');
            mq.addEventListener('change', (e) => {
                this.desktop = e.matches;
                if (e.matches) { this.mobileOpen = false; }
            });
            this.$watch('collapsed', (v) => {
                this.tip = null;
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

    <div @class(['flex bg-shell', 'h-screen overflow-hidden' => $fixed, 'min-h-screen' => ! $fixed])>

        {{-- Sidebar --}}
        <aside id="app-sidebar" aria-label="Sidebar"
            :class="mobileOpen && '!translate-x-0'"
            x-effect="$el.toggleAttribute('inert', !desktop && !mobileOpen)"
            x-on:click="if ($event.target.closest('a')) { mobileOpen = false }"
            class="fs-shell-aside fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col overflow-hidden rounded-r-3xl bg-white shadow-xl lg:sticky lg:top-0 lg:z-30 lg:h-screen lg:shrink-0 lg:translate-x-0 lg:rounded-r-3xl lg:shadow-[6px_0_24px_-14px_rgba(55,64,48,0.35)] lg:ring-1 lg:ring-blush">

            <div class="fs-head flex h-16 shrink-0 items-center justify-between gap-1.5 pl-[1.125rem] pr-2.5">
                <x-ui.brand :href="$home" :name="$brandName" text-class="fs-hide-collapsed"
                    class="fs-brand min-w-0 text-moss" :title="$brandName" />

                {{-- Mobile: close the drawer --}}
                <button type="button" x-on:click="mobileOpen = false" aria-label="Close menu"
                    class="shrink-0 rounded-xl p-2 text-ink/60 transition-colors hover:bg-shell hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sage lg:hidden">
                    <x-ui.icon name="x" class="h-5 w-5" />
                </button>

                {{-- Desktop: collapse / expand the sidebar --}}
                <button type="button" x-on:click="toggle()" aria-controls="app-sidebar" aria-label="Toggle sidebar"
                    :aria-expanded="collapsed ? 'false' : 'true'"
                    x-on:mouseenter="showTip($el, collapsed ? 'Expand sidebar' : 'Collapse sidebar')" x-on:mouseleave="tip = null"
                    class="hidden h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-blush/70 text-ink/60 transition-colors hover:bg-shell hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sage lg:flex">
                    <x-ui.icon name="panel-left-close" class="h-[1.125rem] w-[1.125rem] fs-hide-when-collapsed" />
                    <x-ui.icon name="panel-left-open" class="fs-show-collapsed h-[1.125rem] w-[1.125rem]" />
                </button>
            </div>

            <x-nav.sidebar :menus="$menus" class="min-h-0 flex-1 overflow-y-auto overflow-x-hidden px-3 pb-4 pt-2" />
        </aside>

        {{-- Tooltip for the collapsed rail (the sidebar clips overflow, so it is drawn fixed) --}}
        <div x-show="tip" x-cloak x-transition.opacity.duration.150ms
            :style="tip ? `top:${tip.top}px; left:${tip.left}px` : ''"
            class="pointer-events-none fixed z-[60] -translate-y-1/2 whitespace-nowrap rounded-lg bg-ink px-2.5 py-1.5 text-xs font-medium text-white shadow-lg"
            x-text="tip ? tip.label : ''" role="tooltip"></div>

        {{-- Content column --}}
        <div @class(['flex min-w-0 flex-1 flex-col bg-shell', 'h-screen' => $fixed])>

            {{-- Header: flat bar on the page background --}}
            <div class="sticky top-0 z-20 shrink-0 bg-shell/90 backdrop-blur">
            <header class="relative flex h-16 items-center gap-3 px-4 sm:px-6">
                <button type="button" x-on:click="mobileOpen = !mobileOpen" aria-controls="app-sidebar" aria-label="Open menu"
                    :aria-expanded="mobileOpen ? 'true' : 'false'"
                    class="rounded-xl p-2 text-ink/70 transition-colors hover:bg-shell hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sage lg:hidden">
                    <x-ui.icon name="menu" class="h-5 w-5" />
                </button>

                @if ($fixed)
                    <div class="pointer-events-none absolute left-1/2 hidden -translate-x-1/2 text-center leading-tight sm:block"
                        x-data="{ now: new Date() }" x-init="setInterval(() => now = new Date(), 1000)">
                        <div class="font-mono text-sm font-semibold" x-text="now.toLocaleTimeString('en-GB')"></div>
                        <div class="text-xs text-ink/60"
                            x-text="now.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long' })"></div>
                    </div>
                    <div class="flex-1"></div>
                @else
                    <div class="flex-1"></div>
                @endif

                <div class="ml-auto flex shrink-0 items-center gap-1 sm:gap-2">
                    @unless ($fixed)
                        <x-nav.search :items="$searchItems" />
                    @endunless

                    @if ($bell && $user)
                        <x-nav.bell :href="$bell" :user="$user" />
                    @endif

                    @if ($user)
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
                    @else
                        <a href="{{ $home }}" class="rounded-xl border border-blush bg-white/70 px-4 py-2 text-xs font-semibold uppercase tracking-wider text-moss transition hover:bg-blush">Sign In</a>
                    @endif
                </div>
            </header>
            </div>

            {{-- Page --}}
            @if ($fixed)
                <main class="min-h-0 flex-1 overflow-hidden px-3 pb-3 sm:px-4">
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
