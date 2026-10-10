@props(['href', 'name' => null, 'subtitle' => null])

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'flex items-center gap-3']) }}>
    <img src="{{ asset('images/logo.png') }}" alt="" class="h-9 w-9 rounded-full bg-cream object-contain p-1">
    <div class="leading-tight">
        <div class="text-lg font-bold">{{ $name ?? config('app.name') }}</div>
        @if ($subtitle)
        <div class="text-xs text-cream/60">{{ $subtitle }}</div>
        @endif
    </div>
</a>
