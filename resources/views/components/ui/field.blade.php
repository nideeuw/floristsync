@props(['name', 'label', 'type' => 'text', 'value' => null, 'autocomplete' => null, 'id' => null, 'bag' => 'default', 'hint' => null])

@php($fieldId = $id ?? $name)

<div>
    <label for="{{ $fieldId }}" class="mb-1 block text-sm font-medium">{{ $label }}</label>

    <input id="{{ $fieldId }}" type="{{ $type }}" name="{{ $name }}"
        @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
        @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        {{ $attributes->merge(['class' => 'input']) }}>

    @if ($hint)
        <p class="mt-1 text-xs text-gray-500">{{ $hint }}</p>
    @endif

    @error($name, $bag)
        <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
    @enderror
</div>
