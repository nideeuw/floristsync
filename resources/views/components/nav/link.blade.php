@props(['menu', 'variant' => 'sidebar'])

@php
$state = match (true) {
$variant === 'topbar' && $menu->active => 'bg-white/15 font-semibold text-white',
$menu->active => 'bg-moss font-semibold text-white',
default => 'text-cream/80 hover:bg-white/10',
};
@endphp

<a href="{{ $menu->href }}"
    @class(['flex items-center gap-3 rounded-xl px-3 py-2 text-sm', $state])
    @if ($menu->active) aria-current="page" @endif>
    <x-ui.icon :name="$menu->menu_icon" class="h-4 w-4 shrink-0" />
    <span>{{ $menu->menu_name }}</span>
</a>
