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

    <div class="mx-auto max-w-2xl px-4 py-8 md:py-12"
        x-data="payment({{ \Illuminate\Support\Js::from($snapToken) }}, {{ \Illuminate\Support\Js::from(route('track')) }})"
        x-init="pay()">
        <x-page-header eyebrow="Secure payment" title="Payment" />

        <div class="mt-8 rounded-[2rem] bg-white p-6 shadow-sm ring-1 ring-blush/60 md:p-8">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-display text-3xl font-semibold">Order {{ $transaction->order_number }}</h2>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-petal/40 px-3 py-1 text-xs font-semibold text-ink">
                    <x-ui.icon name="clock" class="h-3.5 w-3.5" />
                    Pay within 60 minutes
                </span>
            </div>

            <ul class="mt-6 space-y-3 text-sm">
                @foreach ($transaction->details as $detail)
                <li class="flex justify-between gap-3">
                    <span>{{ $detail->qty }} x {{ $detail->product->product_name }}</span>
                    <span class="shrink-0 font-medium">{{ rupiah($detail->subtotal) }}</span>
                </li>
                @endforeach
            </ul>

            <div class="mt-5 space-y-2 border-t border-blush/80 pt-5 text-sm">
                <div class="flex justify-between">
                    <span class="text-ink/70">Fulfillment</span>
                    <span class="capitalize">{{ $transaction->fulfillment }}</span>
                </div>
                <div class="flex items-end justify-between pt-3">
                    <span class="font-semibold">Total</span>
                    <span class="text-2xl font-bold text-moss">{{ rupiah($transaction->total) }}</span>
                </div>
            </div>

            <p x-show="message" x-cloak x-text="message" class="mt-5 rounded-xl bg-blush/50 p-3 text-sm"></p>

            <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                <button type="button" class="btn-primary w-full py-3.5 text-base" @click="pay()">Pay now</button>
                <a href="{{ route('track') }}" class="btn-outline w-full py-3.5 text-base">My orders</a>
            </div>

            <p class="mt-4 text-center text-xs text-ink/50">Payment via Midtrans (card, QRIS, e-wallet).</p>
        </div>
    </div>
</x-public-layout>
