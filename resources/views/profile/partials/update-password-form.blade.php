<section>
    <header class="mb-5">
        <h2 class="text-lg font-bold">Password</h2>
        <p class="mt-1 text-sm text-gray-600">Use a long, random password to keep your account secure.</p>
    </header>

    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        @method('PUT')

        <x-ui.field name="current_password" label="Current password" type="password" bag="updatePassword"
            autocomplete="current-password" />
        <x-ui.field name="password" id="new_password" label="New password" type="password" bag="updatePassword"
            autocomplete="new-password" />
        <x-ui.field name="password_confirmation" label="Confirm new password" type="password" bag="updatePassword"
            autocomplete="new-password" />

        <div class="flex items-center gap-4">
            <button class="btn-primary">Update password</button>

            @if (session('status') === 'password-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2500)"
                    class="text-sm font-medium text-moss">Saved.</p>
            @endif
        </div>
    </form>
</section>
