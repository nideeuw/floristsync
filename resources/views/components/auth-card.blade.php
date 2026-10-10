@props(['title', 'subtitle' => null, 'dark' => false, 'home' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="flex min-h-screen items-center justify-center p-4 font-sans text-ink antialiased {{ $dark ? 'bg-ink' : 'bg-cream' }}">
    <div class="w-full max-w-sm">
        <div class="mb-6 text-center {{ $dark ? 'text-cream' : 'text-ink' }}">
            <a @if ($home) href="{{ $home }}" @endif class="inline-block">
                <img src="{{ asset('images/logo.png') }}" alt=""
                    class="mx-auto h-16 w-16 rounded-full bg-cream object-contain p-2">
            </a>
            <h1 class="mt-3 text-2xl font-bold">{{ $title }}</h1>
            @if ($subtitle)
            <p class="mt-1 text-sm opacity-70">{{ $subtitle }}</p>
            @endif
        </div>

        @if (session('status'))
        <div class="mb-4 rounded-xl bg-sage/30 px-3 py-2 text-sm text-ink {{ $dark ? 'bg-cream' : '' }}">
            {{ session('status') }}
        </div>
        @endif

        {{ $slot }}
    </div>
</body>

</html>
