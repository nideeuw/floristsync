@props(['href' => null, 'size' => 'md'])

@php
$name = config('app.name');
$hasSync = \Illuminate\Support\Str::contains($name, 'Sync');
$first = $hasSync ? \Illuminate\Support\Str::before($name, 'Sync') : $name;
$box = $size === 'lg' ? 'h-14 w-14 p-2' : 'h-11 w-11 p-1.5';
$text = $size === 'lg' ? 'text-4xl' : 'text-[1.7rem]';
@endphp

<a href="{{ $href ?? url('/') }}" {{ $attributes->merge(['class' => 'group inline-flex items-center gap-3']) }}>
    <span class="{{ $box }} flex shrink-0 items-center justify-center rounded-full bg-white shadow-sm ring-1 ring-blush transition duration-300 group-hover:scale-105 group-hover:shadow-md">
        <img src="{{ asset('images/logo.png') }}" alt="" class="h-full w-full object-contain">
    </span>
    <span class="font-display {{ $text }} font-semibold leading-none tracking-tight text-ink">{{ $first }}@if ($hasSync)<span class="italic text-moss">Sync</span>@endif</span>
</a>
