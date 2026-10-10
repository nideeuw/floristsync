<x-auth-card title="Verify email" subtitle="One more step" :home="route('home')">
    <div class="card space-y-4 p-6">
        <p class="text-sm opacity-70">
            Thanks for signing up! Please verify your email address by clicking the link we just emailed to you.
            If you did not receive it, we will gladly send another.
        </p>

        @if (session('status') === 'verification-link-sent')
            <div class="rounded-xl bg-sage/30 px-3 py-2 text-sm">
                A new verification link has been sent to your email address.
            </div>
        @endif

        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button class="btn-primary w-full py-3">Resend verification email</button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="w-full text-center text-sm font-semibold text-moss hover:text-ink">Log out</button>
        </form>
    </div>
</x-auth-card>
