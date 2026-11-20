@props(['product'])

@php
$icons = ['flower-2', 'flower', 'sprout', 'leaf'];
$backgrounds = ['bg-blush', 'bg-petal/50', 'bg-sage/40', 'bg-cream'];
$variant = $product->id % 4;
// null for empty or legacy paths (files lost on the old container disk) -> placeholder
$imageUrl = app(\App\Services\ProductImageService::class)->url($product->image);
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center justify-center overflow-hidden ' . $backgrounds[$variant]]) }}>
    @if ($imageUrl)
    <img src="{{ $imageUrl }}" alt="{{ $product->product_name }}" loading="lazy"
        class="h-full w-full object-cover">
    @else
    <x-ui.icon :name="$icons[$variant]" class="h-16 w-16 text-moss/70" />
    @endif
</div>
