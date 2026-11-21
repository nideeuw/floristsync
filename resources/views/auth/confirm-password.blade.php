<x-auth-card title="Confirm password" subtitle="This is a secure area" :home="route('home')">
    <form method="POST" action="{{ route('password.confirm') }}" class="card space-y-4 p-6">
        @csrf

        <p class="text-sm opacity-70">You are logged in as <strong>{{ auth()->user()->name }}</strong> ({{ auth()->user()->email }}). For security, enter <strong>your own password</strong> (the account you are logged in with), not a staff member's password.</p>

        <x-ui.field name="password" label="Your password" type="password" autocomplete="current-password" required autofocus />

        <button class="btn-primary w-full py-3">Confirm</button>
    </form>
</x-auth-card>
