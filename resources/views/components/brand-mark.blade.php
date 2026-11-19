@props(['href' => null, 'size' => 'md', 'light' => false])

@php
$name = config('app.name');
$hasSync = \Illuminate\Support\Str::contains($name, 'Sync');
$first = $hasSync ? \Illuminate\Support\Str::before($name, 'Sync') : $name;
$text = $size === 'lg' ? 'text-4xl' : 'text-[1.7rem]';
@endphp

<a href="{{ $href ?? url('/') }}" {{ $attributes->merge(['class' => 'group inline-flex items-center gap-3']) }}>
    <x-ui.logo :size="$size" tone="light" class="transition duration-300 group-hover:scale-105" />
    <span class="font-display {{ $text }} font-semibold leading-none tracking-tight {{ $light ? 'text-cream' : 'text-ink' }}">{{ $first }}@if ($hasSync)<span class="italic {{ $light ? 'text-petal' : 'text-moss' }}">Sync</span>@endif</span>
</a>