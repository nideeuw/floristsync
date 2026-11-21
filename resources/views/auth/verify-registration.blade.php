<x-member-auth title="Verify your email" heading="Check your email"
    subtitle="We sent a 6-digit code to {{ $maskedEmail }}. It is valid for {{ $minutes }} minutes." :home="route('home')">

    <form method="POST" action="{{ route('register.verify.store') }}" class="space-y-3">
        @csrf

        <x-member-field name="code" label="Verification code" icon="key-round" autocomplete="one-time-code"
            inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus />

        <button class="btn-primary w-full py-3 text-base">Verify and create account</button>
    </form>

    <div class="mt-4 flex flex-col items-center gap-2 text-sm text-ink/70" x-data="{ left: {{ (int) $cooldown }} }"
        x-init="setInterval(() => { if (left > 0) left-- }, 1000)">
        <form method="POST" action="{{ route('register.verify.resend') }}">
            @csrf
            <button class="font-semibold text-moss hover:text-ink disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="left > 0">
                Send a new code<span x-show="left > 0" x-cloak> (in <span x-text="left"></span>s)</span>
            </button>
        </form>

        <form method="POST" action="{{ route('register.verify.cancel') }}">
            @csrf
            <button class="font-semibold text-moss hover:text-ink">Use a different email</button>
        </form>
    </div>
</x-member-auth>
