<x-auth-card title="Create account" subtitle="Join FloristSync to order flowers" :home="route('home')">
    <form method="POST" action="{{ route('register') }}" class="card space-y-4 p-6">
        @csrf

        <x-ui.field name="name" label="Name" autocomplete="name" required autofocus />
        <x-ui.field name="email" label="Email" type="email" autocomplete="username" required />
        <x-ui.field name="password" label="Password" type="password" autocomplete="new-password" required />
        <x-ui.field name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" required />

        <button class="btn-primary w-full py-3">Register</button>
    </form>

    <p class="mt-4 text-center text-sm">
        Already registered?
        <a href="{{ route('login') }}" class="font-semibold text-moss hover:text-ink">Log in</a>
    </p>
</x-auth-card>
