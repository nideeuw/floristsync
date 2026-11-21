<x-public-layout title="Confirm your new email">
    <div class="mx-auto max-w-md px-4 py-8 md:py-12">
        <div class="rounded-[2rem] bg-white p-6 shadow-sm ring-1 ring-blush/60 md:p-8">
            <h1 class="font-display text-3xl font-semibold">Confirm your new email</h1>
            <p class="mt-2 text-sm text-ink/70">
                We sent a 6-digit code to <strong>{{ $newEmail }}</strong>. It is valid for {{ $minutes }} minutes.
                Until you enter it, your account keeps using <strong>{{ auth()->user()->email }}</strong>.
            </p>

            @if (session('status'))
                <p class="mt-4 text-sm font-medium text-moss">{{ session('status') }}</p>
            @endif

            <form method="POST" action="{{ route('profile.email.verify.store') }}" class="mt-5 space-y-3">
                @csrf

                <x-ui.field name="code" label="Verification code" autocomplete="one-time-code" inputmode="numeric"
                    pattern="[0-9]{6}" maxlength="6" required autofocus />

                <button class="btn-primary w-full py-3">Confirm new email</button>
            </form>

            <div class="mt-4 flex flex-col items-center gap-2 text-sm text-ink/70" x-data="{ left: {{ (int) $cooldown }} }"
                x-init="setInterval(() => { if (left > 0) left-- }, 1000)">
                <form method="POST" action="{{ route('profile.email.resend') }}">
                    @csrf
                    <button class="font-semibold text-moss hover:text-ink disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="left > 0">
                        Send a new code<span x-show="left > 0" x-cloak> (in <span x-text="left"></span>s)</span>
                    </button>
                </form>

                <form method="POST" action="{{ route('profile.email.cancel') }}">
                    @csrf
                    <button class="font-semibold text-moss hover:text-ink">Keep my current email</button>
                </form>
            </div>
        </div>
    </div>
</x-public-layout>
