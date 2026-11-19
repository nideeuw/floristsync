@props(['title', 'heading', 'subtitle' => null, 'home' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} - {{ config('app.name') }}</title>
    <style>input:-webkit-autofill{-webkit-box-shadow:0 0 0 1000px #fff inset;-webkit-text-fill-color:#374030}</style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-paper font-sans text-ink antialiased">
    <div class="grid min-h-screen lg:grid-cols-2">

        {{-- Decorative panel (desktop only) --}}
        <x-member-aside :home="$home" />

        {{-- Form side --}}
        <main class="flex items-center justify-center px-4 py-10 sm:px-8">
            <div class="w-full max-w-md">
                <x-brand-mark :href="$home ?? url('/')" class="mb-8 lg:hidden" />

                <a href="{{ $home ?? url('/') }}"
                    class="mb-8 hidden w-fit items-center gap-1 text-sm font-medium text-ink/60 transition hover:text-moss lg:flex">
                    <x-ui.icon name="arrow-left" class="h-4 w-4" />
                    Back to shop
                </a>

                <h1 class="font-display text-5xl font-semibold leading-tight">{{ $heading }}</h1>
                @if ($subtitle)
                <p class="mt-2 text-sm text-ink/60">{{ $subtitle }}</p>
                @endif

                @if (session('status') && session('status') !== 'verification-link-sent')
                <div class="mt-6 rounded-2xl bg-sage/30 px-4 py-3 text-sm text-ink">
                    {{ session('status') }}
                </div>
                @endif

                <div class="mt-8">
                    {{ $slot }}
                </div>
            </div>
        </main>
    </div>
</body>

</html>
