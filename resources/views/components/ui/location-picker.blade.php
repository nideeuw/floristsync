@props([
    'latitude' => null,
    'longitude' => null,
    'address' => null,
    'postalCode' => null,
    'withPostal' => true,
    'addressLabel' => 'Full address',
    'areaLinked' => false,
    'destinationZip' => null,
    'pinHint' => 'Mark your exact spot so the courier can find you more easily.',
])

@php
    $lat = old('latitude', $latitude);
    $lng = old('longitude', $longitude);
    $hasPin = is_numeric($lat) && is_numeric($lng);

    $config = [
        'latitude' => $hasPin ? (float) $lat : null,
        'longitude' => $hasPin ? (float) $lng : null,
        'address' => (string) old('address', $address),
        'postalCode' => (string) old('postal_code', $postalCode),
        'withPostal' => (bool) $withPostal,
        'areaLinked' => (bool) $areaLinked,
        'destinationZip' => $destinationZip ? (string) $destinationZip : null,
        'searchUrl' => url('/api/location/search'),
        'reverseUrl' => url('/api/location/reverse'),
    ];
@endphp

<div x-data="locationPicker({{ Js::from($config) }})"
    @destination-picked.window="onDestination($event.detail)"
    class="space-y-6">

    {{-- Street address + postal code --}}
    <div class="grid gap-4 md:grid-cols-3">
        <div class="{{ $withPostal ? 'md:col-span-2' : 'md:col-span-3' }}">
            <x-ui.textarea name="address" :label="$addressLabel" rows="3"
                :hint="$areaLinked ? 'Street name, building, house number, RT/RW, and landmarks.' : null"
                x-model="address" x-on:input="addressTouched = true" />

            <p x-show="addressTouched && suggested && suggested !== address" x-cloak
                class="mt-2 rounded-xl bg-sage/10 p-3 text-xs text-ink/70">
                Street found for the pin:
                <span class="break-words font-medium" x-text="suggested"></span>
                <button type="button" class="ml-1 font-semibold text-moss underline" @click="useSuggested()">Use this</button>
            </p>
        </div>

        @if ($withPostal)
            <div>
                <x-ui.field name="postal_code" label="Postal code" autocomplete="postal-code"
                    inputmode="numeric" maxlength="10" x-model="postalCode"
                    :hint="$areaLinked ? 'Filled automatically from your area.' : null" />
            </div>
        @endif
    </div>

    {{-- Pin point --}}
    <div class="space-y-3">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h4 class="text-sm font-medium">
                    Pin point <span class="font-normal text-ink/50">(optional)</span>
                </h4>
                <p class="text-xs text-ink/60">{{ $pinHint }}</p>
            </div>

            <button type="button" class="btn-outline shrink-0" @click="locate()" :disabled="locating">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="7" />
                    <circle cx="12" cy="12" r="2" />
                    <path d="M12 2v3M12 19v3M2 12h3M19 12h3" />
                </svg>
                <span x-text="locating ? 'Locating...' : 'Use my location'"></span>
            </button>
        </div>

        @unless ($areaLinked)
            <div class="relative" @click.outside="results = []">
                <input id="location-search" type="text" class="input" autocomplete="off"
                    placeholder="Search a street, building or area"
                    x-model="query" @input="onInput()" @keydown.enter.prevent="searchNow()">

                <ul x-show="results.length" x-cloak
                    class="absolute z-20 mt-1 max-h-64 w-full overflow-auto rounded-2xl border border-blush bg-white shadow-xl">
                    <template x-for="(place, index) in results" :key="index">
                        <li>
                            <button type="button" @click="pick(place)"
                                class="block w-full break-words px-4 py-2.5 text-left text-sm hover:bg-blush/40"
                                x-text="place.label"></button>
                        </li>
                    </template>
                </ul>
                <p x-show="searching" x-cloak class="mt-1 text-xs text-ink/50">Searching...</p>
            </div>
        @endunless

        <p x-show="error" x-cloak x-text="error" class="rounded-xl bg-blush/50 p-3 text-sm"></p>
        <p x-show="notice && !error" x-cloak x-text="notice" class="text-xs text-ink/60"></p>

        <div class="isolate overflow-hidden rounded-2xl border border-blush bg-cream">
            <div x-ref="map" x-show="!mapFailed" class="h-64 w-full md:h-80"></div>
            <p x-show="mapFailed" x-cloak class="p-6 text-center text-sm text-ink/60">
                The map could not be loaded. You can still type your address above.
            </p>
        </div>

        <p x-show="zipMismatch" x-cloak class="rounded-xl bg-petal/30 p-3 text-xs text-ink/80">
            The pin looks like postal code <span class="font-semibold" x-text="pinPostcode"></span>,
            but your area uses <span class="font-semibold" x-text="destZip"></span>.
            Please double-check the area or move the pin.
        </p>

        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 text-xs text-ink/60">
            <p>Tap the map or drag the pin. Detected locations can be a few meters off, so double-check.</p>
            <div x-show="hasPin" x-cloak class="flex items-center gap-3">
                <span x-text="coordinates"></span>
                <button type="button" class="font-semibold text-moss underline" @click="clearPin()">Remove pin</button>
            </div>
        </div>
        <p x-show="resolving" x-cloak class="text-xs text-ink/50">Finding the street for this spot...</p>
    </div>

    <input type="hidden" name="latitude" :value="latitude ?? ''">
    <input type="hidden" name="longitude" :value="longitude ?? ''">

    @if ($errors->has('latitude') || $errors->has('longitude'))
        <p class="text-xs text-red-700">Please pick a valid spot on the map.</p>
    @endif
</div>
