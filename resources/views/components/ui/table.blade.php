@props(['title' => null])

<div {{ $attributes->merge(['class' => 'card overflow-hidden']) }}>
    @if ($title || isset($actions))
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-blush px-5 py-3">
            @if ($title)
                <h2 class="font-semibold">{{ $title }}</h2>
            @endif
            @isset($actions)
                <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    <div class="overflow-x-auto">
        <table class="w-full min-w-[32rem] text-left text-sm">
            <thead class="bg-blush/40 text-xs uppercase text-gray-600">
                <tr class="[&>th]:whitespace-nowrap [&>th]:px-5 [&>th]:py-3">{{ $head }}</tr>
            </thead>
            <tbody class="divide-y divide-blush [&>tr>td]:px-5 [&>tr>td]:py-3">{{ $body }}</tbody>
        </table>
    </div>

    @isset($footer)
        <div class="border-t border-blush px-5 py-3">{{ $footer }}</div>
    @endisset
</div>
