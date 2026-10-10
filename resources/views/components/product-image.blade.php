@props(['product'])

@php
$icons = ['flower-2', 'flower', 'sprout', 'leaf'];
$backgrounds = ['bg-blush', 'bg-petal/50', 'bg-sage/40', 'bg-cream'];
$slot = $product->id % 4;
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center justify-center overflow-hidden ' . $backgrounds[$slot]]) }}>
    @if ($product->image)
    <img src="{{ asset('storage/' . $product->image) }}" alt="{{ $product->product_name }}"
        class="h-full w-full object-cover">
    @else
    <x-ui.icon :name="$icons[$slot]" class="h-16 w-16 text-moss/70" />
    @endif
</div>
