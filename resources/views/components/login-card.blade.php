@props(['title', 'subtitle' => null, 'action', 'button' => 'Log in', 'dark' => false, 'home' => null, 'forgot' => null])

@php
$otherKey = collect($errors->keys())->diff(['email', 'password'])->first();
@endphp

<x-auth-card :title="$title" :subtitle="$subtitle" :dark="$dark" :home="$home">
    <form method="POST" action="{{ $action }}" class="card space-y-4 p-6">
        @csrf

        @if ($otherKey)
        <div class="rounded-xl bg-red-100 px-3 py-2 text-sm text-red-800">{{ $errors->first($otherKey) }}</div>
        @endif

        <x-ui.field name="email" label="Email" type="email" autocomplete="username" required autofocus />
        <x-ui.field name="password" label="Password" type="password" autocomplete="current-password" required />

        <div class="flex items-center justify-between text-sm">
            <label class="flex items-center gap-2">
                <input type="checkbox" name="remember" class="accent-moss"> Remember me
            </label>

            @if ($forgot)
            <a href="{{ $forgot }}" class="font-medium text-moss hover:text-ink">Forgot password?</a>
            @endif
        </div>

        <button class="btn-primary w-full py-3">{{ $button }}</button>
    </form>

    {{ $slot }}
</x-auth-card>
