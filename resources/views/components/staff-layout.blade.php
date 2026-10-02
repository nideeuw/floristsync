@props(['title' => null])

@php
$role = auth()->user()->role?->role_name;

$menu = collect([
['label' => 'Dashboard', 'route' => 'admin.dashboard', 'pattern' => 'admin.dashboard', 'roles' => ['admin', 'owner']],
['label' => 'Orders', 'route' => 'admin.orders', 'pattern' => 'admin.orders*', 'roles' => ['admin', 'cashier']],
['label' => 'Products', 'route' => 'admin.products.index', 'pattern' => 'admin.products.*', 'roles' => ['admin']],
['label' => 'Categories', 'route' => 'admin.categories.index', 'pattern' => 'admin.categories.*', 'roles' => ['admin']],
['label' => 'Reports', 'route' => 'admin.reports', 'pattern' => 'admin.reports', 'roles' => ['admin', 'owner']],
])->filter(fn ($item) => in_array($role, $item['roles'], true));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title . ' - ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-cream font-sans text-ink antialiased" x-data="{ open: false }">

    {{-- Mobile top bar --}}
    <div class="flex items-center justify-between bg-ink px-4 py-3 text-cream md:hidden">
        <span class="font-bold">FloristSync</span>
        <button @click="open = !open" class="text-xl" aria-label="Menu">☰</button>
    </div>

    <div class="flex min-h-screen">

        {{-- Sidebar --}}
        <aside :class="open ? 'flex' : 'hidden'"
            class="fixed inset-y-0 left-0 z-40 w-60 shrink-0 flex-col bg-ink text-cream md:static md:flex">
            <a href="{{ route('home') }}" class="flex items-center gap-2 px-5 py-5">
                <img src="{{ asset('images/logo.png') }}" alt="" class="h-9 w-9 rounded-full bg-cream object-contain p-1">
                <span class="text-lg font-bold">FloristSync</span>
            </a>

            <nav class="flex-1 space-y-1 px-3">
                @foreach ($menu as $item)
                <a href="{{ route($item['route']) }}"
                    class="block rounded-xl px-3 py-2 text-sm {{ request()->routeIs($item['pattern']) ? 'bg-moss font-semibold text-white' : 'text-cream/80 hover:bg-white/10' }}">
                    {{ $item['label'] }}
                </a>
                @endforeach
            </nav>

            <div class="border-t border-white/10 p-4 text-sm">
                <div class="font-semibold">{{ auth()->user()->name }}</div>
                <div class="mb-2 text-xs text-cream/60">{{ ucfirst($role) }}</div>
                <a href="{{ route('home') }}" class="mr-3 text-cream/80 hover:text-white">View store</a>
                <a href="{{ route('profile.edit') }}" class="mr-3 text-cream/80 hover:text-white">Profile</a>
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="text-cream/80 hover:text-white">Log out</button>
                </form>
            </div>
        </aside>

        {{-- Content --}}
        <div class="flex min-w-0 flex-1 flex-col">
            @if ($title)
            <header class="flex items-center justify-between gap-3 border-b border-blush bg-white px-6 py-4">
                <h1 class="text-xl font-semibold">{{ $title }}</h1>
                @isset($actions)
                <div class="flex items-center gap-2">{{ $actions }}</div>
                @endisset
            </header>
            @endif

            <main class="flex-1 p-6">
                @if (session('success'))
                <div class="mb-4 rounded-xl bg-sage/30 px-4 py-3 text-sm">{{ session('success') }}</div>
                @endif

                @if (session('error'))
                <div class="mb-4 rounded-xl bg-red-100 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
                @endif

                @if ($errors->any())
                <div class="mb-4 rounded-xl bg-red-100 px-4 py-3 text-sm text-red-800">
                    <ul class="list-inside list-disc">
                        @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>
</body>

</html>
