<x-public-layout title="Checkout">
    <script>
        function checkout(subtotal, method) {
            return {
                method,
                subtotal,
                // Dummy shipping cost. The real cost comes from RajaOngkir in a later step.
                get shipping() { return this.method === 'delivery' ? 25000 : 0; },
                get total() { return this.subtotal + this.shipping; },
                rupiah(n) {
                    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n);
                },
            };
        }
    </script>

    <div class="mx-auto max-w-7xl px-4 py-10"
         x-data="checkout({{ $subtotal }}, '{{ old('fulfillment', 'pickup') }}')">
        <h1 class="mb-6 text-3xl font-bold">Checkout</h1>

        @guest
            <div class="mb-6 rounded-xl bg-blush/60 px-4 py-3 text-sm">
                You are checking out as a guest.
                <a href="{{ route('login') }}" class="font-semibold text-moss hover:underline">Log in</a> or
                <a href="{{ route('register') }}" class="font-semibold text-moss hover:underline">register</a>
                to track your orders and receive notifications.
            </div>
        @endguest

        <form method="POST" action="{{ route('checkout.store') }}" class="grid gap-6 lg:grid-cols-3">
            @csrf

            <div class="space-y-6 lg:col-span-2">
                {{-- Contact --}}
                <div class="card p-6">
                    <h2 class="mb-4 text-lg font-bold">Contact</h2>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Full name</label>
                            <input type="text" name="name" required class="input"
                                   value="{{ old('name', auth()->user()?->name) }}">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Phone</label>
                            <input type="text" name="phone" required class="input"
                                   value="{{ old('phone', auth()->user()?->phone) }}">
                        </div>
                        <div class="md:col-span-2">
                            <label class="mb-1 block text-sm font-medium">Email (optional)</label>
                            <input type="email" name="email" class="input"
                                   value="{{ old('email', auth()->user()?->email) }}">
                        </div>
                    </div>
                </div>

                {{-- Fulfillment --}}
                <div class="card p-6">
                    <h2 class="mb-4 text-lg font-bold">How do you want to receive it?</h2>

                    <div class="grid gap-3 md:grid-cols-2">
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-blush p-4"
                               :class="method === 'pickup' ? 'border-moss ring-2 ring-sage/40' : ''">
                            <input type="radio" name="fulfillment" value="pickup" x-model="method" class="mt-1 accent-moss">
                            <span>
                                <span class="block font-semibold">Pick up in store</span>
                                <span class="text-xs text-gray-600">Free. We will tell you when it is ready.</span>
                            </span>
                        </label>

                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-blush p-4"
                               :class="method === 'delivery' ? 'border-moss ring-2 ring-sage/40' : ''">
                            <input type="radio" name="fulfillment" value="delivery" x-model="method" class="mt-1 accent-moss">
                            <span>
                                <span class="block font-semibold">Delivery</span>
                                <span class="text-xs text-gray-600">Shipped by courier to your address.</span>
                            </span>
                        </label>
                    </div>

                    <div x-show="method === 'delivery'" x-cloak class="mt-5 grid gap-4 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label class="mb-1 block text-sm font-medium">City / district</label>
                            <input type="text" name="destination" class="input"
                                   placeholder="Search destination (RajaOngkir search comes next)"
                                   value="{{ old('destination') }}">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Courier</label>
                            <select name="courier" class="input">
                                @foreach (['jne' => 'JNE', 'tiki' => 'TIKI', 'pos' => 'POS Indonesia', 'sicepat' => 'SiCepat', 'jnt' => 'J&T'] as $code => $label)
                                    <option value="{{ $code }}" @selected(old('courier') === $code)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Service</label>
                            <select name="service" class="input">
                                <option value="REG">REG - Rp 25.000 (2-3 days)</option>
                                <option value="YES">YES - Rp 45.000 (1 day)</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="mb-1 block text-sm font-medium">Full address</label>
                            <textarea name="address" rows="3" class="input">{{ old('address') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Summary --}}
            <div>
                <div class="card p-6">
                    <h2 class="text-lg font-bold">Order Summary</h2>

                    <ul class="mt-4 space-y-2 text-sm">
                        @foreach ($items as $item)
                            <li class="flex justify-between gap-3">
                                <span>{{ $item->qty }} x {{ $item->product->product_name }}</span>
                                <span class="shrink-0">{{ rupiah($item->subtotal) }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <div class="mt-4 space-y-1 border-t border-blush pt-4 text-sm">
                        <div class="flex justify-between">
                            <span>Subtotal</span>
                            <span x-text="rupiah(subtotal)"></span>
                        </div>
                        <div class="flex justify-between">
                            <span>Shipping</span>
                            <span x-text="shipping === 0 ? 'Free' : rupiah(shipping)"></span>
                        </div>
                        <div class="flex justify-between pt-2 text-lg font-bold">
                            <span>Total</span>
                            <span x-text="rupiah(total)"></span>
                        </div>
                    </div>

                    <button class="btn-primary mt-6 w-full">Place Order &amp; Pay</button>
                    <p class="mt-2 text-center text-xs text-gray-500">Payment via Midtrans (QRIS, VA, e-wallet).</p>
                </div>
            </div>
        </form>
    </div>
</x-public-layout>
