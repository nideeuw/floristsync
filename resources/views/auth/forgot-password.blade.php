<x-auth-card title="Forgot password" subtitle="We will email you a reset link" :home="route('home')">
    <form method="POST" action="{{ route('password.email') }}" class="card space-y-4 p-6">
        @csrf

        <p class="text-sm opacity-70">
            Enter your email address and we will send you a link to choose a new password.
        </p>

        <x-ui.field name="email" label="Email" type="email" required autofocus />

        <button class="btn-primary w-full py-3">Email reset link</button>
    </form>

    <p class="mt-4 text-center text-sm">
        <a href="{{ route('login') }}" class="font-semibold text-moss hover:text-ink">Back to login</a>
    </p>
</x-auth-card>
