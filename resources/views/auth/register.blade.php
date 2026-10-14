<x-member-auth title="Register" heading="Create your account"
    subtitle="Join {{ config('app.name') }} to order flowers in minutes." :home="route('home')">

    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf

        <x-member-field name="name" label="Name" icon="user" autocomplete="name" required autofocus />
        <x-member-field name="email" label="Email" type="email" icon="mail" autocomplete="username" required />
        <x-member-field name="password" label="Password" type="password" icon="lock" autocomplete="new-password" required />
        <x-member-field name="password_confirmation" label="Confirm password" type="password" icon="lock-keyhole"
            autocomplete="new-password" required />

        <button class="btn-primary w-full py-3.5 text-base">Register</button>
    </form>

    <p class="mt-8 text-center text-sm text-ink/70">
        Already registered?
        <a href="{{ route('login') }}" class="font-semibold text-moss hover:text-ink">Log in</a>
    </p>
</x-member-auth>
