@props(['title' => null])

@php
$user = auth()->user();
@endphp

<x-layouts.base :title="$title" x-data="{ sidebarOpen: false }">

    {{-- Mobile top bar --}}
    <div class="flex items-center justify-between bg-ink px-4 py-3 text-cream md:hidden">
        <span class="font-bold">{{ config('app.name') }}</span>
        <button type="button" @click="sidebarOpen = !sidebarOpen" class="p-1" aria-label="Toggle menu">
            <x-ui.icon name="menu" class="h-6 w-6" />
        </button>
    </div>

    <div class="flex min-h-screen">

        {{-- Sidebar --}}
        <aside :class="sidebarOpen ? 'flex' : 'hidden'"
            class="fixed inset-y-0 left-0 z-40 w-60 shrink-0 flex-col bg-ink text-cream md:static md:flex">
            <x-ui.brand :href="route('admin.dashboard')" class="px-5 py-5" />

            <x-nav.sidebar :menus="$menuTree" class="flex-1 overflow-y-auto px-3" />

            <div class="border-t border-white/10 p-4 text-sm">
                <div class="font-semibold">{{ $user->name }}</div>
                <div class="mb-2 text-xs text-cream/60">{{ str((string) $user->role?->role_name)->headline() }}</div>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="flex items-center gap-2 text-cream/80 hover:text-white">
                        <x-ui.icon name="log-out" class="h-4 w-4" />
                        Log out
                    </button>
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
                <x-ui.flash />

                {{ $slot }}
            </main>
        </div>
    </div>
</x-layouts.base>
