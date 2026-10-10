@props(['name', 'label', 'type' => 'text', 'value' => null, 'autocomplete' => null])

<div>
    <label for="{{ $name }}" class="mb-1 block text-sm font-medium">{{ $label }}</label>

    <input id="{{ $name }}" type="{{ $type }}" name="{{ $name }}"
        @if ($type !=='password' ) value="{{ old($name, $value) }}" @endif
        @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        {{ $attributes->merge(['class' => 'input']) }}>

    @error($name)
    <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
    @enderror
</div>
