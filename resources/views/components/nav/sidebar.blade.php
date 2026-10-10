@props(['menus'])

<nav {{ $attributes->merge(['class' => 'space-y-1']) }} aria-label="Main navigation">
    @foreach ($menus as $menu)
    @if ($menu->menu_behavior === 'group')
    <div x-data="{ open: @js($menu->active) }">
        <button type="button" @click="open = !open"
            class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-sm text-cream/80 hover:bg-white/10">
            <x-ui.icon :name="$menu->menu_icon" class="h-4 w-4 shrink-0" />
            <span class="flex-1 text-left">{{ $menu->menu_name }}</span>
            <x-ui.icon name="chevron-down" class="h-4 w-4 shrink-0 transition-transform"
                ::class="open && 'rotate-180'" />
        </button>

        <div x-show="open" x-cloak class="mt-1 space-y-1 pl-4">
            @foreach ($menu->children as $child)
            <x-nav.link :menu="$child" />
            @endforeach
        </div>
    </div>
    @else
    <x-nav.link :menu="$menu" />
    @endif
    @endforeach
</nav>
