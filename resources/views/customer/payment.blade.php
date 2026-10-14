<x-public-layout title="Payment">
    <script src="{{ $snapUrl }}" data-client-key="{{ $clientKey }}"></script>
    <script>
        function payment(token, trackUrl) {
            return {
                token,
                trackUrl,
                message: '',
                pay() {
                    this.message = '';

                    if (!window.snap) {
                        this.message = 'Payment window could not be loaded. Please refresh the page.';
                        return;
                    }

                    window.snap.pay(this.token, {
                        onSuccess: () => {
                            window.location.href = this.trackUrl;
                        },
                        onPending: () => {
                            window.location.href = this.trackUrl;
                        },
                        onError: () => {
                            this.message = 'Payment failed. Please try again.';
                        },
                        onClose: () => {
                            this.message = 'You closed the payment window. Your order is saved, you can still pay before it expires.';
                        },
                    });
                },
            };
        }
    </script>

    <div class="mx-auto max-w-2xl px-4 py-10"
        x-data="payment(@json($snapToken), @json(route('track')))"
        x-init="pay()">
        <h1 class="mb-6 text-3xl font-bold">Payment</h1>

        <div class="card p-6">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-lg font-bold">Order {{ $transaction->order_number }}</h2>
                <span class="text-xs text-gray-600">Pay within 60 minutes</span>
            </div>

            <ul class="mt-4 space-y-2 text-sm">
                @foreach ($transaction->details as $detail)
                <li class="flex justify-between gap-3">
                    <span>{{ $detail->qty }} x {{ $detail->product->product_name }}</span>
                    <span class="shrink-0">{{ rupiah($detail->subtotal) }}</span>
                </li>
                @endforeach
            </ul>

            <div class="mt-4 space-y-1 border-t border-blush pt-4 text-sm">
                <div class="flex justify-between">
                    <span>Fulfillment</span>
                    <span class="capitalize">{{ $transaction->fulfillment }}</span>
                </div>
                <div class="flex justify-between pt-2 text-lg font-bold">
                    <span>Total</span>
                    <span>{{ rupiah($transaction->total) }}</span>
                </div>
            </div>

            <p x-show="message" x-cloak x-text="message" class="mt-4 rounded-xl bg-blush/50 p-3 text-sm"></p>

            <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                <button type="button" class="btn-primary w-full" @click="pay()">Pay Now</button>
                <a href="{{ route('track') }}" class="btn-primary w-full text-center">My Orders</a>
            </div>

            <p class="mt-2 text-center text-xs text-gray-500">Payment via Midtrans (card, QRIS, e-wallet).</p>
        </div>
    </div>
</x-public-layout>
