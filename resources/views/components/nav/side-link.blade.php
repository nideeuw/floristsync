@props(['menu'])

<a href="{{ $menu->href }}"
    x-on:mouseenter="showTip($el, @js($menu->menu_name))" x-on:mouseleave="tip = null" x-on:focus="showTip($el, @js($menu->menu_name))" x-on:blur="tip = null"
    @class([
        'group flex items-center gap-3 rounded-2xl py-2.5 pl-[0.9375rem] pr-3 text-sm transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sage',
        'bg-sage/25 font-semibold text-ink' => $menu->active,
        'font-medium text-ink/70 hover:bg-shell hover:text-ink' => ! $menu->active,
    ])
    @if ($menu->active) aria-current="page" @endif>
    <x-ui.icon :name="$menu->menu_icon"
        class="h-[1.125rem] w-[1.125rem] shrink-0 transition-colors {{ $menu->active ? 'text-moss' : 'text-ink/50 group-hover:text-ink' }}" />
    <span class="fs-hide-collapsed overflow-hidden whitespace-nowrap">{{ $menu->menu_name }}</span>
</a>
