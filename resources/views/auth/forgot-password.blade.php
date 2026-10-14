<x-member-auth title="Forgot password" heading="Reset your password"
    subtitle="We will email you a link to choose a new password." :home="route('home')">

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf

        <p class="text-sm text-ink/60">
            Enter your email address and we will send you a link to choose a new password.
        </p>

        <x-member-field name="email" label="Email" type="email" icon="mail" required autofocus />

        <button class="btn-primary w-full py-3.5 text-base">Email reset link</button>
    </form>

    <p class="mt-8 text-center text-sm">
        <a href="{{ route('login') }}" class="inline-flex items-center gap-1 font-semibold text-moss hover:text-ink">
            <x-ui.icon name="arrow-left" class="h-4 w-4" />
            Back to login
        </a>
    </p>
</x-member-auth>
