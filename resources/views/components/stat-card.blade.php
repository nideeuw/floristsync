@props(['label', 'value', 'hint' => null])

<div class="card fs-stat p-5 transition hover:-translate-y-0.5 hover:shadow-md">
    <div class="text-sm font-medium text-ink/60">{{ $label }}</div>
    <div data-countup class="mt-2 text-3xl font-bold tracking-tight">{{ $value }}</div>
    @if ($hint)
    <div class="mt-1 text-xs text-ink/50">{{ $hint }}</div>
    @endif
</div>