<section>
    <header class="mb-5">
        <h2 class="text-lg font-bold">Account &amp; shipping address</h2>
        <p class="mt-1 text-sm text-gray-600">This information is used to fill your checkout automatically.</p>
    </header>

    <form method="POST" action="{{ route('profile.update') }}" class="space-y-6">
        @csrf
        @method('PATCH')

        <div class="grid gap-4 md:grid-cols-2">
            <x-ui.field name="name" label="Full name" :value="$user->name" autocomplete="name" required />
            <x-ui.field name="phone" label="Phone" type="tel" :value="$user->phone" autocomplete="tel" />
            <div class="md:col-span-2">
                <x-ui.field name="email" label="Email" type="email" :value="$user->email" autocomplete="username" required />
            </div>
        </div>

        <div class="space-y-4 border-t border-blush pt-6">
            <h3 class="font-semibold">Shipping address</h3>

            <x-ui.textarea name="address" label="Full address" :value="$user->address" />

            <div class="grid gap-4 md:grid-cols-2">
                {{-- TODO(team): replace with RajaOngkir destination search; it must fill destination_id and destination_label together. --}}
                <x-ui.field name="destination_label" label="City / district" :value="$user->destination_label" />
                <x-ui.field name="postal_code" label="Postal code" :value="$user->postal_code" autocomplete="postal-code" />
            </div>
        </div>

        <div class="flex items-center gap-4">
            <button class="btn-primary">Save changes</button>

            @if (session('status') === 'profile-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2500)"
                    class="text-sm font-medium text-moss">Saved.</p>
            @endif
        </div>
    </form>
</section>
