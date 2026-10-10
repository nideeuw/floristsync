@props(['title' => null, 'suffix' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ collect([$title, $suffix ?? config('app.name')])->filter()->implode(' - ') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body {{ $attributes->merge(['class' => 'bg-cream font-sans text-ink antialiased']) }}>
    {{ $slot }}
</body>

</html>
