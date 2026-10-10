@props(['links'])

<div {{ $attributes->merge(['class' => 'space-y-1']) }}>
    @foreach ($links as $link)
    <x-nav.link :menu="$link" variant="dropdown" />
    @endforeach

    <x-nav.logout :action="route('logout')" class="w-full gap-3 rounded-xl px-3 py-2 text-sm text-ink hover:bg-blush" />
</div>
