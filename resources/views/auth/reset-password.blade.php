<x-auth-card title="Reset password" subtitle="Choose a new password" :home="route('home')">
    <form method="POST" action="{{ route('password.store') }}" class="card space-y-4 p-6">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-ui.field name="email" label="Email" type="email" :value="$request->email" autocomplete="username" required autofocus />
        <x-ui.field name="password" label="New password" type="password" autocomplete="new-password" required />
        <x-ui.field name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" required />

        <button class="btn-primary w-full py-3">Reset password</button>
    </form>
</x-auth-card>
