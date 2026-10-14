@props(['title', 'subtitle' => null, 'action', 'button' => 'Log in', 'dark' => false, 'home' => null, 'forgot' => null, 'badge' => null, 'headline' => null, 'points' => []])

@php
$otherKey = collect($errors->keys())->diff(['email', 'password'])->first();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}</title>
    <style>input:-webkit-autofill{-webkit-box-shadow:0 0 0 1000px #fff inset;-webkit-text-fill-color:#374030}</style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen font-sans text-ink antialiased {{ $dark ? 'bg-ink' : 'bg-paper' }}">
    <div class="grid min-h-screen lg:grid-cols-2">

        {{-- Brand panel (desktop only) --}}
        <aside class="relative hidden overflow-hidden lg:flex lg:flex-col lg:justify-between lg:p-12
            {{ $dark ? 'bg-gradient-to-br from-ink via-[#2b3426] to-moss/70 text-cream' : 'bg-gradient-to-br from-blush via-cream to-petal/50 text-ink' }}">
            <div class="pointer-events-none absolute -left-20 -top-20 h-80 w-80 rounded-full blur-3xl {{ $dark ? 'bg-petal/15' : 'bg-white/50' }}"></div>
            <div class="pointer-events-none absolute -bottom-24 -right-10 h-96 w-96 rounded-full blur-3xl {{ $dark ? 'bg-sage/25' : 'bg-sage/30' }}"></div>

            <x-brand-mark :href="$home ?? url('/')" size="lg" :light="$dark" class="relative" />

            <div class="relative max-w-md">
                @if ($badge)
                <span class="inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-xs font-semibold uppercase tracking-[0.2em]
                    {{ $dark ? 'bg-white/10 text-petal' : 'bg-white/70 text-moss' }}">
                    <x-ui.icon name="shield-check" class="h-4 w-4" />
                    {{ $badge }}
                </span>
                @endif

                @if ($headline)
                <h2 class="mt-5 font-display text-5xl font-semibold leading-[1.05]">{{ $headline }}</h2>
                @endif

                @if (count($points))
                <ul class="mt-8 space-y-3">
                    @foreach ($points as $point)
                    <li class="flex items-center gap-4 rounded-2xl px-4 py-3 backdrop-blur {{ $dark ? 'bg-white/10' : 'bg-white/60' }}">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $dark ? 'bg-white/10 text-petal' : 'bg-blush text-moss' }}">
                            <x-ui.icon :name="$point['icon']" class="h-5 w-5" />
                        </span>
                        <span class="text-sm font-medium">{{ $point['text'] }}</span>
                    </li>
                    @endforeach
                </ul>
                @endif
            </div>

            <p class="relative text-xs {{ $dark ? 'text-cream/50' : 'text-ink/50' }}">
                &copy; {{ date('Y') }} {{ config('app.name') }}
            </p>
        </aside>

        {{-- Form side --}}
        <main class="flex items-center justify-center px-4 py-10 sm:px-8">
            <div class="w-full max-w-md">
                <x-brand-mark :href="$home ?? url('/')" :light="$dark" class="mb-8 lg:hidden" />

                <div class="rounded-[2rem] bg-white p-7 shadow-xl ring-1 sm:p-9 {{ $dark ? 'ring-white/10' : 'ring-blush/60' }}">
                    @if ($badge)
                    <span class="inline-flex items-center gap-2 rounded-full bg-blush/60 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-moss lg:hidden">
                        {{ $badge }}
                    </span>
                    @endif

                    <h1 class="mt-3 font-display text-4xl font-semibold leading-tight lg:mt-0">{{ $title }}</h1>
                    @if ($subtitle)
                    <p class="mt-2 text-sm text-ink/60">{{ $subtitle }}</p>
                    @endif

                    @if (session('status') && session('status') !== 'verification-link-sent')
                    <div class="mt-5 rounded-2xl bg-sage/30 px-4 py-3 text-sm">{{ session('status') }}</div>
                    @endif

                    <form method="POST" action="{{ $action }}" class="mt-7 space-y-5">
                        @csrf

                        @if ($otherKey)
                        <div class="rounded-2xl bg-red-100 px-4 py-3 text-sm text-red-800">{{ $errors->first($otherKey) }}</div>
                        @endif

                        <x-member-field name="email" label="Email" type="email" icon="mail" autocomplete="username" required autofocus />
                        <x-member-field name="password" label="Password" type="password" icon="lock" autocomplete="current-password" required />

                        <div class="flex items-center justify-between text-sm">
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="remember" class="rounded accent-moss"> Remember me
                            </label>

                            @if ($forgot)
                            <a href="{{ $forgot }}" class="font-medium text-moss hover:text-ink">Forgot password?</a>
                            @endif
                        </div>

                        <button class="btn-primary w-full py-3.5 text-base">{{ $button }}</button>
                    </form>

                    {{ $slot }}
                </div>

                @if ($home)
                <a href="{{ $home }}" class="mt-6 flex items-center justify-center gap-1 text-sm font-medium transition
                    {{ $dark ? 'text-cream/60 hover:text-white' : 'text-ink/60 hover:text-moss' }}">
                    <x-ui.icon name="arrow-left" class="h-4 w-4" />
                    Back to shop
                </a>
                @endif
            </div>
        </main>
    </div>
</body>

</html>
