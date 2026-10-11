@props(['menu'])

<a href="{{ $menu->href }}" title="{{ $menu->menu_name }}"
    :class="collapsed && 'md:justify-center md:px-0'"
    @class([
        'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm transition',
        'bg-moss font-semibold text-white shadow-sm' => $menu->active,
        'text-cream/75 hover:bg-white/10 hover:text-white' => ! $menu->active,
    ])
    @if ($menu->active) aria-current="page" @endif>
    <x-ui.icon :name="$menu->menu_icon" class="h-[1.125rem] w-[1.125rem] shrink-0" />
    <span class="truncate" :class="collapsed && 'md:hidden'">{{ $menu->menu_name }}</span>
</a>
