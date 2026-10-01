@props(['product'])

@php
    $emojis = ['🌷', '🌸', '🌹', '💐', '🌼', '🌺'];
    $backgrounds = ['bg-blush', 'bg-petal/50', 'bg-sage/40', 'bg-cream'];
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center justify-center overflow-hidden ' . $backgrounds[$product->id % 4]]) }}>
    @if ($product->image)
        <img src="{{ asset('storage/' . $product->image) }}" alt="{{ $product->product_name }}"
             class="h-full w-full object-cover">
    @else
        <span class="text-6xl">{{ $emojis[$product->id % 6] }}</span>
    @endif
</div>
