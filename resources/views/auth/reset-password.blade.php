<x-member-auth title="Reset password" heading="Choose a new password"
    subtitle="Pick a strong password you have not used before." :home="route('home')">

    <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-member-field name="email" label="Email" type="email" icon="mail" :value="$request->email"
            autocomplete="username" required autofocus />
        <x-member-field name="password" label="New password" type="password" icon="lock" autocomplete="new-password" required />
        <x-member-field name="password_confirmation" label="Confirm password" type="password" icon="lock-keyhole"
            autocomplete="new-password" required />

        <button class="btn-primary w-full py-3.5 text-base">Reset password</button>
    </form>
</x-member-auth>
