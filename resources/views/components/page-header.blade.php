@props(['title', 'eyebrow' => null, 'description' => null])

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-end justify-between gap-4']) }}>
    <div>
        @if ($eyebrow)
        <span class="eyebrow">{{ $eyebrow }}</span>
        @endif
        <h1 class="section-title mt-2">{{ $title }}</h1>
        @if ($description)
        <p class="mt-3 max-w-xl text-ink/60">{{ $description }}</p>
        @endif
    </div>
    @if ($slot->isNotEmpty())
    <div>{{ $slot }}</div>
    @endif
</div>
