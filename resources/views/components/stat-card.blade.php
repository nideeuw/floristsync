@props(['label', 'value', 'hint' => null])

<div class="card p-5 transition-shadow hover:shadow-md">
    <div class="text-sm font-medium text-ink/60">{{ $label }}</div>
    <div class="mt-2 text-3xl font-bold tracking-tight">{{ $value }}</div>
    @if ($hint)
    <div class="mt-1 text-xs text-ink/50">{{ $hint }}</div>
    @endif
</div>
