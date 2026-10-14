@props(['menus'])

<nav {{ $attributes->merge(['class' => 'space-y-1']) }} aria-label="Main navigation">
    @foreach ($menus as $menu)
    @if ($menu->menu_behavior === 'group')
    <div class="pt-4 first:pt-0">
        <div class="fs-hide-collapsed px-3 pb-1.5 text-[11px] font-semibold uppercase tracking-wider text-gray-400">
            {{ $menu->menu_name }}
        </div>
        <div class="fs-show-collapsed mx-3 mb-2 border-t border-blush"></div>

        <div class="space-y-0.5">
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
