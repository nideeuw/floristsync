@props(['name', 'label', 'type' => 'text', 'value' => null, 'autocomplete' => null, 'id' => null, 'icon' => null, 'bag' => 'default'])

@php($fieldId = $id ?? $name)

<div @if ($type === 'password') x-data="{ show: false }" @endif>
    <label for="{{ $fieldId }}" class="mb-1.5 block text-sm font-medium">{{ $label }}</label>

    <div class="relative">
        @if ($icon)
        <x-ui.icon :name="$icon" class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-moss/70" />
        @endif

        <input id="{{ $fieldId }}" type="{{ $type }}" name="{{ $name }}"
            @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
            @if ($type === 'password') :type="show ? 'text' : 'password'" @endif
            @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
            {{ $attributes->merge(['class' => 'input rounded-full py-3 ' . ($icon ? 'pl-12' : 'pl-5') . ($type === 'password' ? ' pr-12' : ' pr-5')]) }}>

        @if ($type === 'password')
        <button type="button" @click="show = !show" :aria-label="show ? 'Hide password' : 'Show password'"
            class="absolute right-2 top-1/2 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full text-ink/50 transition hover:bg-blush/50 hover:text-moss">
            <x-ui.icon name="eye" class="h-5 w-5" x-show="!show" />
            <x-ui.icon name="eye-off" class="h-5 w-5" x-show="show" x-cloak />
        </button>
        @endif
    </div>

    @error($name, $bag)
    <p class="mt-1.5 text-xs text-red-700">{{ $message }}</p>
    @enderror
</div>
