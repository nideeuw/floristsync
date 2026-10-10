@props(['title' => 'POS'])

<x-layouts.base :title="$title" :suffix="config('app.name') . ' POS'" class="h-screen overflow-hidden">

    <header class="flex h-14 items-center justify-between bg-ink px-4 text-cream"
        x-data="{ now: new Date() }" x-init="setInterval(() => now = new Date(), 1000)">
        <x-ui.brand :href="route('cashier.pos')" :name="config('app.name') . ' POS'"
            :subtitle="'Cashier: ' . auth()->user()->name" />

        <div class="hidden text-center leading-tight sm:block">
            <div class="font-mono text-lg" x-text="now.toLocaleTimeString('en-GB')"></div>
            <div class="text-xs text-cream/60"
                x-text="now.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long' })"></div>
        </div>

        <nav class="flex items-center gap-2 text-sm" aria-label="Main navigation">
            @foreach ($menuLinks as $link)
            <x-nav.link :menu="$link" variant="topbar" />
            @endforeach

            <form method="POST" action="{{ route('cashier.logout') }}">
                @csrf
                <button type="submit"
                    class="flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 hover:bg-white/20">
                    <x-ui.icon name="log-out" class="h-4 w-4" />
                    Log out
                </button>
            </form>
        </nav>
    </header>

    {{ $slot }}
</x-layouts.base>
