@props(['size' => 'md', 'tone' => 'light'])

@php
$box = match ($size) {
    'sm' => 'h-9 w-9',
    'lg' => 'h-14 w-14',
    'xl' => 'h-16 w-16',
    default => 'h-11 w-11',
};
// light: no background, the logo sits directly on the page; dark: cream disc so it stays visible
$disc = $tone === 'dark' ? 'rounded-full bg-cream p-1.5' : '';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex shrink-0 items-center justify-center {$disc} {$box}"]) }}>
    <img src="{{ asset('images/logo.png') }}" alt="" class="h-full w-full object-contain">
</span>