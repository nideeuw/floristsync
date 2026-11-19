@php
    $hasErrors = isset($errors) && $errors->any();
@endphp

@if (session('success') || session('error') || $hasErrors)
<div {{ $attributes }}>
    @if (session('success'))
    <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 5000)" x-show="show"
        x-transition:enter="transition duration-500 ease-out"
        x-transition:enter-start="opacity-0 -translate-y-3"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition duration-300 ease-in"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0 -translate-y-2"
        role="status" class="relative mb-4 overflow-hidden rounded-xl bg-sage/30 px-4 py-3 pr-10 text-sm">
        {{ session('success') }}
        <button type="button" x-on:click="show = false" aria-label="Dismiss"
            class="absolute right-2 top-2 rounded-full p-1 text-ink/50 transition hover:bg-white/60 hover:text-ink">
            <x-ui.icon name="x" class="h-4 w-4" />
        </button>
        <span class="fs-flash-bar"></span>
    </div>
    @endif

    @if (session('error'))
    <div x-data="{ show: true }" x-show="show"
        x-transition:enter="transition duration-500 ease-out"
        x-transition:enter-start="opacity-0 -translate-y-3"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition duration-300 ease-in"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0 -translate-y-2"
        role="alert" class="relative mb-4 rounded-xl bg-red-100 px-4 py-3 pr-10 text-sm text-red-800">
        {{ session('error') }}
        <button type="button" x-on:click="show = false" aria-label="Dismiss"
            class="absolute right-2 top-2 rounded-full p-1 text-red-800/60 transition hover:bg-white/60 hover:text-red-800">
            <x-ui.icon name="x" class="h-4 w-4" />
        </button>
    </div>
    @endif

    @if ($hasErrors)
    <div x-data="{ show: true }" x-show="show"
        x-transition:enter="transition duration-500 ease-out"
        x-transition:enter-start="opacity-0 -translate-y-3"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition duration-300 ease-in"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0 -translate-y-2"
        role="alert" class="relative mb-4 rounded-xl bg-red-100 px-4 py-3 pr-10 text-sm text-red-800">
        <ul class="list-inside list-disc">
            @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" x-on:click="show = false" aria-label="Dismiss"
            class="absolute right-2 top-2 rounded-full p-1 text-red-800/60 transition hover:bg-white/60 hover:text-red-800">
            <x-ui.icon name="x" class="h-4 w-4" />
        </button>
    </div>
    @endif
</div>
@endif