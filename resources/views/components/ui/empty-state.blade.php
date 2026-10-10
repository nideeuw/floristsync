@props(['title', 'icon' => 'inbox', 'description' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center rounded-2xl border border-blush bg-white px-6 py-16 text-center']) }}>
    <x-ui.icon :name="$icon" class="mb-3 h-10 w-10 text-moss" />
    <h2 class="text-lg font-semibold">{{ $title }}</h2>
    @if ($description)
    <p class="mt-1 max-w-sm text-sm text-ink/60">{{ $description }}</p>
    @endif
    @if ($slot->isNotEmpty())
    <div class="mt-5">{{ $slot }}</div>
    @endif
</div>
