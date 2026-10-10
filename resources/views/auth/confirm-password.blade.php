<x-auth-card title="Confirm password" subtitle="This is a secure area" :home="route('home')">
    <form method="POST" action="{{ route('password.confirm') }}" class="card space-y-4 p-6">
        @csrf

        <p class="text-sm opacity-70">Please confirm your password before continuing.</p>

        <x-ui.field name="password" label="Password" type="password" autocomplete="current-password" required autofocus />

        <button class="btn-primary w-full py-3">Confirm</button>
    </form>
</x-auth-card>
