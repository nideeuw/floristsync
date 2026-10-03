@props(['title' => 'POS'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} - {{ config('app.name') }} POS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="h-screen overflow-hidden bg-cream font-sans text-ink antialiased">

    <header class="flex h-14 items-center justify-between bg-ink px-4 text-cream"
        x-data="{ now: new Date() }" x-init="setInterval(() => now = new Date(), 1000)">
        <div class="flex items-center gap-3">
            <img src="{{ asset('images/logo.png') }}" alt="" class="h-9 w-9 rounded-full bg-cream object-contain p-1">
            <div class="leading-tight">
                <div class="font-bold">FloristSync POS</div>
                <div class="text-xs text-cream/60">Cashier: {{ auth()->user()->name }}</div>
            </div>
        </div>

        <div class="hidden text-center leading-tight sm:block">
            <div class="font-mono text-lg" x-text="now.toLocaleTimeString('en-GB')"></div>
            <div class="text-xs text-cream/60" x-text="now.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long' })"></div>
        </div>

        <nav class="flex items-center gap-5 text-sm">
            <a href="{{ route('cashier.pos') }}" class="font-semibold text-white">POS</a>

            <form method="POST" action="{{ route('cashier.logout') }}">
                @csrf
                <button type="submit" class="rounded-full bg-white/10 px-3 py-1.5 hover:bg-white/20">Log out</button>
            </form>
        </nav>
    </header>

    {{ $slot }}
</body>

</html>
