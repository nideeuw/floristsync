<x-staff-layout title="Store Settings" subtitle="Store profile, opening hours and shipping origin">
    <form method="POST" action="{{ route('admin.settings.update') }}" class="max-w-3xl space-y-6">
        @csrf
        @method('PATCH')

        {{-- Store profile --}}
        <section class="card space-y-4 p-6">
            <h2 class="text-base font-semibold">Store profile</h2>

            <x-ui.field name="store_name" label="Store name" :value="$form['store_name']" required maxlength="100" />

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field name="phone" label="Phone" type="tel" :value="$form['phone']" autocomplete="tel" />
                <x-ui.field name="whatsapp_number" label="WhatsApp number" type="tel" :value="$form['whatsapp_number']"
                    hint="Example: 081234567890 (saved as 6281234567890). Used in the footer and the chat button." required />
            </div>

            <x-ui.textarea name="address" label="Address" :value="$form['address']" rows="3" />
        </section>

        {{-- Opening hours --}}
        <section class="card p-6">
            <h2 class="text-base font-semibold">Opening hours</h2>
            <p class="mt-1 text-xs text-gray-500">Shown in the public footer. Times are in WIB.</p>

            <div class="mt-4 divide-y divide-blush">
                @foreach ($form['hours'] as $day => $row)
                    @php
                        $closed = count(old()) ? (bool) old("hours.$day.closed") : $row['closed'];
                    @endphp

                    <div x-data="{ closed: @js($closed) }" class="flex flex-col gap-2 py-3 sm:flex-row sm:flex-wrap sm:items-center sm:gap-4">
                        <div class="w-28 text-sm font-medium">{{ ucfirst($day) }}</div>

                        <div class="grid flex-1 grid-cols-2 gap-2">
                            <input type="time" name="hours[{{ $day }}][open]" value="{{ old("hours.$day.open", $row['open']) }}"
                                :disabled="closed" class="input disabled:opacity-40" aria-label="{{ ucfirst($day) }} opening time">
                            <input type="time" name="hours[{{ $day }}][close]" value="{{ old("hours.$day.close", $row['close']) }}"
                                :disabled="closed" class="input disabled:opacity-40" aria-label="{{ ucfirst($day) }} closing time">
                        </div>

                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="hours[{{ $day }}][closed]" value="1" x-model="closed" class="accent-moss">
                            Closed
                        </label>

                        @error("hours.$day.open")
                            <p class="text-xs text-red-700 sm:basis-full">{{ $message }}</p>
                        @enderror
                        @error("hours.$day.close")
                            <p class="text-xs text-red-700 sm:basis-full">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Shipping origin --}}
        <section class="card space-y-4 p-6">
            <h2 class="text-base font-semibold">Shipping origin</h2>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field name="shipping_origin_id" label="RajaOngkir origin ID" type="number" min="1" inputmode="numeric"
                    :value="$form['shipping_origin_id']" hint="The origin ID used to calculate shipping costs." />
                <x-ui.field name="shipping_origin_label" label="Origin label" :value="$form['shipping_origin_label']"
                    hint="For example: the district and city of your store." maxlength="255" />
            </div>
        </section>

        <div class="flex gap-2">
            <button class="btn-primary">Save settings</button>
            <a href="{{ route('admin.settings') }}" class="btn-outline">Reset</a>
        </div>
    </form>
</x-staff-layout>
