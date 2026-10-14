@props(['menu'])

<a href="{{ $menu->href }}" title="{{ $menu->menu_name }}"
    @class([
        'fs-center-collapsed group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sage',
        'bg-sage/30 font-semibold text-moss' => $menu->active,
        'font-medium text-ink/70 hover:bg-blush/50 hover:text-ink' => ! $menu->active,
    ])
    @if ($menu->active) aria-current="page" @endif>
    <x-ui.icon :name="$menu->menu_icon"
        class="h-[1.125rem] w-[1.125rem] shrink-0 transition-colors {{ $menu->active ? 'text-moss' : 'text-ink/50 group-hover:text-ink' }}" />
    <span class="fs-hide-collapsed truncate">{{ $menu->menu_name }}</span>
</a>
