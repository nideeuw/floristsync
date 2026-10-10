@props(['name', 'label', 'value' => null, 'rows' => 3, 'hint' => null])

<div>
    <label for="{{ $name }}" class="mb-1 block text-sm font-medium">{{ $label }}</label>

    <textarea id="{{ $name }}" name="{{ $name }}" rows="{{ $rows }}"
        {{ $attributes->merge(['class' => 'input']) }}>{{ old($name, $value) }}</textarea>

    @if ($hint)
        <p class="mt-1 text-xs text-gray-500">{{ $hint }}</p>
    @endif

    @error($name)
        <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
    @enderror
</div>
