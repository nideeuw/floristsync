@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title . ' - ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
</head>

<body class="min-h-screen bg-paper font-sans text-ink antialiased">
    <div class="relative flex min-h-screen flex-col items-center justify-between overflow-hidden bg-gradient-to-b from-blush/40 via-paper to-paper px-4 py-8">
        <div class="pointer-events-none absolute -left-24 -top-24 h-80 w-80 rounded-full bg-blush/50 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-24 -right-16 h-96 w-96 rounded-full bg-sage/20 blur-3xl"></div>

        <x-brand-mark :href="url('/')" class="relative" />

        <main class="relative w-full">{{ $slot }}</main>

        <x-copyright class="relative text-xs text-ink/40" />
    </div>
</body>

</html>