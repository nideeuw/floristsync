@props(['label', 'value', 'hint' => null])

<div class="card p-5">
    <div class="text-sm text-gray-500">{{ $label }}</div>
    <div class="mt-1 text-2xl font-extrabold">{{ $value }}</div>
    @if ($hint)
    <div class="mt-1 text-xs text-gray-500">{{ $hint }}</div>
    @endif
</div>
