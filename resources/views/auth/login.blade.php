@php
$otherKey = collect($errors->keys())->diff(['email', 'password'])->first();
$forgot = Route::has('password.request') ? route('password.request') : null;
@endphp

<x-member-auth title="Log in" heading="Welcome back"
    subtitle="Sign in to check out faster and track your orders." :home="route('home')">

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
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

        <button class="btn-primary w-full py-3.5 text-base">Log in</button>
    </form>

    <p class="mt-8 text-center text-sm text-ink/70">
        New here?
        <a href="{{ route('register') }}" class="font-semibold text-moss hover:text-ink">Create an account</a>
    </p>
</x-member-auth>
