<section x-data="{ open: {{ $errors->userDeletion->isNotEmpty() ? 'true' : 'false' }} }">
    <header class="mb-5">
        <h2 class="font-display text-3xl font-semibold text-red-700">Deactivate account</h2>
        <p class="mt-1 text-sm text-ink/60">Your account will be deactivated and you will be logged out. Your order history is kept.</p>
    </header>

    <button type="button" x-show="!open" @click="open = true"
        class="inline-flex items-center justify-center rounded-full border border-red-300 px-5 py-2.5 text-sm font-semibold text-red-700 transition hover:bg-red-50">
        Deactivate account
    </button>

    <form x-show="open" x-cloak method="POST" action="{{ route('profile.destroy') }}" class="space-y-4">
        @csrf
        @method('DELETE')

        <x-ui.field name="password" id="delete_password" label="Confirm with your password" type="password"
            bag="userDeletion" autocomplete="current-password" />

        <div class="flex flex-wrap items-center gap-3">
            <button type="button" @click="open = false" class="btn-secondary">Cancel</button>
            <button class="inline-flex items-center justify-center rounded-full bg-red-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-red-700">
                Yes, deactivate my account
            </button>
        </div>
    </form>
</section>
