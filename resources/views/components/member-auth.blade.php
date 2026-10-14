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
        <aside class="relative hidden overflow-hidden bg-gradient-to-br from-blush via-cream to-petal/50 lg:flex lg:flex-col lg:justify-between lg:p-12">
            <div class="pointer-events-none absolute -left-20 -top-20 h-80 w-80 rounded-full bg-white/50 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-24 -right-10 h-96 w-96 rounded-full bg-sage/30 blur-3xl"></div>

            <x-brand-mark :href="$home ?? url('/')" size="lg" class="relative" />

            <div class="relative mx-auto w-full max-w-xs animate-float-slow">
                <svg viewBox="0 0 320 380" class="w-full" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <linearGradient id="fsaVase" x1="0" y1="0" x2="1" y2="0">
                            <stop offset="0" stop-color="#FFFFFF" />
                            <stop offset="0.55" stop-color="#F7EEE9" />
                            <stop offset="1" stop-color="#E3D2CA" />
                        </linearGradient>
                        <g id="fsaBloom">
                            <ellipse cx="0" cy="-22" rx="13" ry="22" />
                            <ellipse cx="0" cy="-22" rx="13" ry="22" transform="rotate(60)" />
                            <ellipse cx="0" cy="-22" rx="13" ry="22" transform="rotate(120)" />
                            <ellipse cx="0" cy="-22" rx="13" ry="22" transform="rotate(180)" />
                            <ellipse cx="0" cy="-22" rx="13" ry="22" transform="rotate(240)" />
                            <ellipse cx="0" cy="-22" rx="13" ry="22" transform="rotate(300)" />
                            <circle r="8" fill="#F4D58D" stroke="none" />
                        </g>
                        <path id="fsaLeaf" d="M0 0C14-14 38-14 54 0 38 14 14 14 0 0Z" />
                    </defs>

                    <g fill="none" stroke="#7E8C69" stroke-width="4.5" stroke-linecap="round">
                        <path d="M160 270C160 220 160 160 160 105" />
                        <path d="M160 270C142 225 112 190 92 145" />
                        <path d="M160 270C180 225 212 190 232 142" />
                        <path d="M160 270C128 245 90 225 66 200" />
                        <path d="M160 270C192 245 230 225 254 198" />
                    </g>
                    <g fill="#8FA07C">
                        <use href="#fsaLeaf" transform="translate(158 235) rotate(-140) scale(0.85)" />
                        <use href="#fsaLeaf" transform="translate(162 222) rotate(-35) scale(0.85)" />
                        <use href="#fsaLeaf" transform="translate(120 200) rotate(-165) scale(0.65)" />
                        <use href="#fsaLeaf" transform="translate(205 200) rotate(-15) scale(0.65)" />
                    </g>
                    <g stroke="#FFFFFF" stroke-opacity="0.5" stroke-width="1.5">
                        <use href="#fsaBloom" fill="#F6B0BB" transform="translate(160 100) scale(1.3)" />
                        <use href="#fsaBloom" fill="#FFFFFF" transform="translate(90 142) scale(1.05)" />
                        <use href="#fsaBloom" fill="#E98FA0" transform="translate(232 138) scale(1.1)" />
                        <use href="#fsaBloom" fill="#F1C8CB" transform="translate(66 198) scale(0.8)" />
                        <use href="#fsaBloom" fill="#FFFFFF" transform="translate(254 196) scale(0.8)" />
                    </g>
                    <ellipse cx="160" cy="366" rx="70" ry="8" fill="#374030" opacity="0.12" />
                    <path d="M124 262C119 305 90 325 102 352C108 366 212 366 218 352C230 325 201 305 196 262Z" fill="url(#fsaVase)" />
                    <ellipse cx="160" cy="262" rx="36" ry="8" fill="#E3D2CA" />
                </svg>
            </div>

            <p class="relative max-w-sm font-display text-4xl font-semibold italic leading-tight text-ink">
                Flowers that say it from the heart.
            </p>
        </aside>

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
