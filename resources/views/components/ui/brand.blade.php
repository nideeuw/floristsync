@props(['href', 'name' => null, 'subtitle' => null, 'textClass' => '', 'tone' => 'light'])

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'flex items-center gap-3']) }}>
    <x-ui.logo size="sm" :tone="$tone" />
    <div class="min-w-0 leading-tight {{ $textClass }}">
        <div class="truncate text-lg font-bold">{{ $name ?? config('app.name') }}</div>
        @if ($subtitle)
        <div class="text-xs text-cream/60">{{ $subtitle }}</div>
        @endif
    </div>
</a>
