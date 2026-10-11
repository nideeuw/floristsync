@props(['menus'])

<nav {{ $attributes->merge(['class' => 'space-y-1']) }} aria-label="Main navigation">
    @foreach ($menus as $menu)
    @if ($menu->menu_behavior === 'group')
    <div class="pt-3">
        <div :class="collapsed && 'md:hidden'"
            class="px-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-cream/50">
            {{ $menu->menu_name }}
        </div>
        <div x-show="collapsed" x-cloak class="mx-3 mb-2 hidden border-t border-white/10 md:block"></div>

        <div class="space-y-1">
            @foreach ($menu->children as $child)
            <x-nav.side-link :menu="$child" />
            @endforeach
        </div>
    </div>
    @else
    <x-nav.side-link :menu="$menu" />
    @endif
    @endforeach
</nav>
