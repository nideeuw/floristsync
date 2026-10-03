@props(['title', 'subtitle' => null, 'action', 'button' => 'Log in', 'dark' => false])

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
            <img src="{{ asset('images/logo.png') }}" alt=""
                class="mx-auto h-16 w-16 rounded-full bg-cream object-contain p-2">
            <h1 class="mt-3 text-2xl font-bold">{{ $title }}</h1>
            @if ($subtitle)
            <p class="mt-1 text-sm opacity-70">{{ $subtitle }}</p>
            @endif
        </div>

        <form method="POST" action="{{ $action }}" class="card space-y-4 p-6">
            @csrf

            @if ($errors->any())
            <div class="rounded-xl bg-red-100 px-3 py-2 text-sm text-red-800">{{ $errors->first() }}</div>
            @endif

            <div>
                <label for="email" class="mb-1 block text-sm font-medium">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}"
                    required autofocus autocomplete="username" class="input">
            </div>

            <div>
                <label for="password" class="mb-1 block text-sm font-medium">Password</label>
                <input id="password" type="password" name="password"
                    required autocomplete="current-password" class="input">
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="remember" class="accent-moss"> Remember me
            </label>

            <button class="btn-primary w-full py-3">{{ $button }}</button>
        </form>
    </div>
</body>

</html>
