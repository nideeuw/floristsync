<x-public-layout title="Payment">
    <script src="{{ $snapUrl }}" data-client-key="{{ $clientKey }}"></script>
    <script>
        function customerPayment(config) {
            return {
                orderId: config.orderId,
                transactionId: config.transactionId,
                total: config.total,
                clientKey: config.clientKey,
                csrfToken: config.csrfToken,
                trackUrl: config.trackUrl,

                method: config.initialState?.method || '',
                activeCharge: config.initialState?.method ? {
                    method: config.initialState.method,
                    qr_url: config.initialState.qr_url,
                    deeplink: config.initialState.deeplink,
                    qr_string: config.initialState.qr_string,
                    snap_token: config.initialState.snap_token,
                } : null,

                chargeStatus: config.initialState?.method ? 'pending' : 'idle',
                loading: false,
                errorMessage: '',

                remainingSeconds: config.initialState?.remaining_seconds ?? 3600,
                timerInterval: null,
                pollInterval: null,

                init() {
                    this.startTimer();
                    if (this.activeCharge && this.chargeStatus === 'pending') {
                        this.startPolling();
                    }
                },

                get formattedTime() {
                    if (this.remainingSeconds <= 0) return '00:00';
                    const m = Math.floor(this.remainingSeconds / 60);
                    const s = this.remainingSeconds % 60;
                    return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
                },

                startTimer() {
                    if (this.timerInterval) clearInterval(this.timerInterval);
                    this.timerInterval = setInterval(() => {
                        if (this.remainingSeconds > 0) {
                            this.remainingSeconds--;
                        } else {
                            clearInterval(this.timerInterval);
                            this.chargeStatus = 'expire';
                            this.stopPolling();
                        }
                    }, 1000);
                },

                async selectMethod(m) {
                    if (this.loading || this.remainingSeconds <= 0) return;
                    if (this.method === m && this.activeCharge && this.chargeStatus === 'pending') return;

                    this.method = m;
                    this.loading = true;
                    this.errorMessage = '';
                    this.chargeStatus = 'generating';
                    this.stopPolling();

                    try {
                        const res = await fetch(`/api/customer/orders/${this.transactionId}/charge`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken,
                            },
                            body: JSON.stringify({ method: m }),
                        });

                        const json = await res.json();
                        if (!res.ok) {
                            this.chargeStatus = 'error';
                            this.errorMessage = json.message || 'Failed to start payment. Please try again.';
                            return;
                        }

                        this.activeCharge = json.data;
                        this.chargeStatus = 'pending';

                        if (m === 'card' || m === 'credit_card') {
                            this.payWithCard();
                        }

                        this.startPolling();
                    } catch (err) {
                        this.chargeStatus = 'error';
                        this.errorMessage = 'Network error. Please try again.';
                    } finally {
                        this.loading = false;
                    }
                },

                payWithCard() {
                    if (!this.activeCharge?.snap_token) return;
                    if (!window.snap) {
                        this.errorMessage = 'Card payment window could not load. Please refresh.';
                        return;
                    }

                    window.snap.pay(this.activeCharge.snap_token, {
                        onSuccess: () => {
                            this.checkStatusNow();
                        },
                        onPending: () => {
                            this.checkStatusNow();
                        },
                        onError: () => {
                            this.errorMessage = 'Card payment was not completed. Please try again or choose another method.';
                        },
                        onClose: () => {
                            this.checkStatusNow();
                        },
                    });
                },

                startPolling() {
                    this.stopPolling();
                    this.pollInterval = setInterval(() => {
                        this.checkStatusNow();
                    }, 2500);
                },

                stopPolling() {
                    if (this.pollInterval) {
                        clearInterval(this.pollInterval);
                        this.pollInterval = null;
                    }
                },

                async checkStatusNow() {
                    if (this.chargeStatus === 'settlement' || this.remainingSeconds <= 0) {
                        this.stopPolling();
                        return;
                    }

                    try {
                        const res = await fetch(`/api/customer/orders/${this.transactionId}/payment-status`, {
                            headers: { 'Accept': 'application/json' },
                        });

                        if (!res.ok) return;

                        const data = await res.json();

                        if (data.is_paid) {
                            this.stopPolling();
                            this.chargeStatus = 'settlement';
                            setTimeout(() => {
                                window.location.href = data.redirect_url || this.trackUrl;
                            }, 1200);
                        } else if (data.is_failed) {
                            this.stopPolling();
                            this.chargeStatus = data.status || 'cancel';
                        }
                    } catch (e) {
                        console.error('Polling status error', e);
                    }
                },

                async changeMethod() {
                    this.stopPolling();
                    this.loading = true;
                    this.errorMessage = '';

                    try {
                        await fetch(`/api/customer/orders/${this.transactionId}/cancel-charge`, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken,
                            },
                        });
                    } catch (e) {
                        console.error('Cancel charge error', e);
                    } finally {
                        this.method = '';
                        this.activeCharge = null;
                        this.chargeStatus = 'idle';
                        this.loading = false;
                    }
                },
            };
        }
    </script>

    <div class="mx-auto max-w-5xl px-4 py-8 md:py-12"
        x-data="customerPayment({
            orderId: {{ \Illuminate\Support\Js::from($transaction->order_number) }},
            transactionId: {{ \Illuminate\Support\Js::from($transaction->id) }},
            total: {{ \Illuminate\Support\Js::from($transaction->total) }},
            clientKey: {{ \Illuminate\Support\Js::from($clientKey) }},
            csrfToken: {{ \Illuminate\Support\Js::from(csrf_token()) }},
            trackUrl: {{ \Illuminate\Support\Js::from(route('track', ['order' => $transaction->order_number])) }},
            initialState: {{ \Illuminate\Support\Js::from($initialState ?? null) }},
        })">

        <x-page-header eyebrow="Secure Payment" title="Complete Your Payment" />

        <div class="mt-8 grid gap-8 lg:grid-cols-12 items-start">
            {{-- Left column: Payment Method & Active Charge --}}
            <div class="space-y-6 lg:col-span-7 w-full">
                {{-- Method Selection Screen --}}
                <div x-show="chargeStatus === 'idle'" class="rounded-[2rem] bg-white p-6 shadow-sm ring-1 ring-blush/60 md:p-8">
                    <h3 class="font-display text-xl font-bold text-ink">Choose Payment Method</h3>
                    <p class="mt-1 text-xs text-ink/60">Select your preferred payment method below. No virtual accounts; only instant, refundable methods.</p>

                    <div class="mt-6 space-y-3">
                        {{-- QRIS Option --}}
                        <button type="button" @click="selectMethod('qris')" :disabled="loading || remainingSeconds <= 0"
                            class="group flex w-full items-center justify-between rounded-2xl border-2 border-blush p-4 text-left transition hover:border-moss hover:bg-cream/30 focus:outline-none focus:ring-2 focus:ring-moss">
                            <div class="flex items-center gap-4">
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-petal/30 text-moss transition group-hover:bg-moss group-hover:text-white">
                                    <x-ui.icon name="qr-code" class="h-6 w-6" />
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-ink">QRIS</span>
                                        <span class="rounded bg-red-600 px-1.5 py-0.5 text-[10px] font-bold text-white">GPN</span>
                                    </div>
                                    <p class="text-xs text-ink/60">GoPay, OVO, Dana, ShopeePay, BCA & any banking app</p>
                                </div>
                            </div>
                            <x-ui.icon name="chevron-right" class="h-5 w-5 text-ink/30 transition group-hover:translate-x-0.5 group-hover:text-moss" />
                        </button>

                        {{-- GoPay Option --}}
                        <button type="button" @click="selectMethod('gopay')" :disabled="loading || remainingSeconds <= 0"
                            class="group flex w-full items-center justify-between rounded-2xl border-2 border-blush p-4 text-left transition hover:border-moss hover:bg-cream/30 focus:outline-none focus:ring-2 focus:ring-moss">
                            <div class="flex items-center gap-4">
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-petal/30 text-moss transition group-hover:bg-moss group-hover:text-white">
                                    <x-ui.icon name="wallet" class="h-6 w-6" />
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-ink">GoPay</span>
                                        <span class="rounded bg-moss/20 px-1.5 py-0.5 text-[10px] font-bold text-moss">E-Wallet</span>
                                    </div>
                                    <p class="text-xs text-ink/60">Scan QR or open directly in your Gojek app</p>
                                </div>
                            </div>
                            <x-ui.icon name="chevron-right" class="h-5 w-5 text-ink/30 transition group-hover:translate-x-0.5 group-hover:text-moss" />
                        </button>

                        {{-- ShopeePay Option --}}
                        <button type="button" @click="selectMethod('shopeepay')" :disabled="loading || remainingSeconds <= 0"
                            class="group flex w-full items-center justify-between rounded-2xl border-2 border-blush p-4 text-left transition hover:border-moss hover:bg-cream/30 focus:outline-none focus:ring-2 focus:ring-moss">
                            <div class="flex items-center gap-4">
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-petal/30 text-moss transition group-hover:bg-moss group-hover:text-white">
                                    <x-ui.icon name="smartphone" class="h-6 w-6" />
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-ink">ShopeePay</span>
                                        <span class="rounded bg-orange-100 px-1.5 py-0.5 text-[10px] font-bold text-orange-700">App</span>
                                    </div>
                                    <p class="text-xs text-ink/60">Instant jump to Shopee application</p>
                                </div>
                            </div>
                            <x-ui.icon name="chevron-right" class="h-5 w-5 text-ink/30 transition group-hover:translate-x-0.5 group-hover:text-moss" />
                        </button>

                        {{-- Credit / Debit Card Option --}}
                        <button type="button" @click="selectMethod('card')" :disabled="loading || remainingSeconds <= 0"
                            class="group flex w-full items-center justify-between rounded-2xl border-2 border-blush p-4 text-left transition hover:border-moss hover:bg-cream/30 focus:outline-none focus:ring-2 focus:ring-moss">
                            <div class="flex items-center gap-4">
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-petal/30 text-moss transition group-hover:bg-moss group-hover:text-white">
                                    <x-ui.icon name="credit-card" class="h-6 w-6" />
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-ink">Credit / Debit Card</span>
                                        <span class="rounded bg-blue-100 px-1.5 py-0.5 text-[10px] font-bold text-blue-700">3DS</span>
                                    </div>
                                    <p class="text-xs text-ink/60">Visa, Mastercard, JCB (secure 3D Secure verification)</p>
                                </div>
                            </div>
                            <x-ui.icon name="chevron-right" class="h-5 w-5 text-ink/30 transition group-hover:translate-x-0.5 group-hover:text-moss" />
                        </button>
                    </div>

                    <div x-show="errorMessage" x-cloak class="mt-4 rounded-xl bg-red-50 p-3 text-xs text-red-700">
                        <span x-text="errorMessage"></span>
                    </div>
                </div>

                {{-- Active Charge Screen --}}
                <div x-show="chargeStatus !== 'idle'" x-cloak class="rounded-[2rem] bg-white p-6 shadow-sm ring-1 ring-blush/60 md:p-8">
                    {{-- Header with Change Method button --}}
                    <div class="flex items-center justify-between border-b border-blush/80 pb-4">
                        <div>
                            <span class="text-xs font-semibold uppercase tracking-wider text-moss">Payment method</span>
                            <h3 class="font-display text-xl font-bold capitalize text-ink"
                                x-text="method === 'credit_card' || method === 'card' ? 'Credit / Debit Card' : method"></h3>
                        </div>
                        <button type="button" @click="changeMethod()" :disabled="loading"
                            class="btn-outline inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold">
                            <x-ui.icon name="arrow-left" class="h-3.5 w-3.5" />
                            <span>Change method</span>
                        </button>
                    </div>

                    {{-- Loading / Generating state --}}
                    <div x-show="chargeStatus === 'generating'" class="flex h-64 flex-col items-center justify-center">
                        <div class="h-10 w-10 animate-spin rounded-full border-4 border-moss border-t-transparent"></div>
                        <p class="mt-4 text-sm font-medium text-ink/70">Connecting to Midtrans...</p>
                    </div>

                    {{-- Pending Active Method Display --}}
                    <div x-show="chargeStatus === 'pending'" class="mt-6">
                        {{-- QRIS View --}}
                        <div x-show="method === 'qris'">
                            <x-qr-display
                                status="chargeStatus"
                                qr-url="activeCharge?.qr_url"
                                error="errorMessage"
                                hint="Open your mobile banking or e-wallet app, tap scan, and pay the exact amount."
                                :show-error="false"
                            />
                        </div>

                        {{-- GoPay View --}}
                        <div x-show="method === 'gopay'" class="flex flex-col items-center">
                            <template x-if="activeCharge && activeCharge.qr_url">
                                <x-qr-display
                                    status="chargeStatus"
                                    qr-url="activeCharge?.qr_url"
                                    error="errorMessage"
                                    waiting-text="Waiting for GoPay payment..."
                                    hint="Scan the QR code with GoPay or your Gojek app"
                                    :show-error="false"
                                />
                            </template>

                            <template x-if="activeCharge && activeCharge.deeplink">
                                <div class="mt-4 w-full text-center">
                                    <p class="text-xs text-ink/60 mb-2">On mobile? Open your Gojek app directly:</p>
                                    <a :href="activeCharge.deeplink"
                                        class="btn-primary inline-flex w-full items-center justify-center gap-2 py-3 text-sm font-semibold shadow-md">
                                        <x-ui.icon name="external-link" class="h-4 w-4" />
                                        <span>Open Gojek App to Pay</span>
                                    </a>
                                </div>
                            </template>
                        </div>

                        {{-- ShopeePay View --}}
                        <div x-show="method === 'shopeepay'" class="flex flex-col items-center text-center">
                            <div class="flex h-20 w-20 items-center justify-center rounded-3xl bg-orange-50 text-orange-600 shadow-sm ring-1 ring-orange-200">
                                <x-ui.icon name="smartphone" class="h-10 w-10" />
                            </div>
                            <h4 class="mt-4 text-lg font-bold text-ink">Pay with ShopeePay</h4>
                            <p class="mt-1 max-w-sm text-xs text-ink/60">
                                Click the button below to launch the Shopee application and complete your payment.
                            </p>

                            <template x-if="activeCharge && activeCharge.deeplink">
                                <div class="mt-5 w-full">
                                    <a :href="activeCharge.deeplink"
                                        class="btn-primary inline-flex w-full items-center justify-center gap-2 py-3.5 text-base font-semibold shadow-md">
                                        <x-ui.icon name="external-link" class="h-4 w-4" />
                                        <span>Open Shopee App</span>
                                    </a>
                                </div>
                            </template>

                            <div class="mt-4 flex items-center gap-2 text-xs text-gray-600">
                                <span class="inline-block h-2 w-2 animate-ping rounded-full bg-moss"></span>
                                <span>Waiting for ShopeePay confirmation...</span>
                            </div>
                        </div>

                        {{-- Credit Card View --}}
                        <div x-show="method === 'credit_card' || method === 'card'" class="flex flex-col items-center text-center">
                            <div class="flex h-20 w-20 items-center justify-center rounded-3xl bg-blue-50 text-blue-600 shadow-sm ring-1 ring-blue-200">
                                <x-ui.icon name="credit-card" class="h-10 w-10" />
                            </div>
                            <h4 class="mt-4 text-lg font-bold text-ink">Card Payment Window</h4>
                            <p class="mt-1 max-w-sm text-xs text-ink/60">
                                A secure payment window powered by Midtrans 3D Secure is opened. If you closed it, click below to reopen.
                            </p>
                            <button type="button" @click="payWithCard()"
                                class="btn-primary mt-5 inline-flex w-full items-center justify-center gap-2 py-3.5 text-base font-semibold shadow-md">
                                <x-ui.icon name="lock" class="h-4 w-4" />
                                <span>Pay with Card</span>
                            </button>
                        </div>

                        {{-- Manual Refresh button --}}
                        <div class="mt-6 border-t border-blush/80 pt-4 flex flex-col items-center gap-2">
                            <button type="button" @click="checkStatusNow()"
                                class="text-xs font-semibold text-moss hover:underline inline-flex items-center gap-1.5">
                                <x-ui.icon name="refresh-cw" class="h-3.5 w-3.5" />
                                <span>I have completed payment (check status)</span>
                            </button>
                            <p class="text-[11px] text-ink/40">Status updates automatically once payment is verified.</p>
                        </div>
                    </div>

                    {{-- Settlement / Success state --}}
                    <div x-show="chargeStatus === 'settlement'" x-cloak class="flex flex-col items-center py-8 text-center">
                        <div class="flex h-16 w-16 items-center justify-center rounded-full bg-green-100 text-green-600 ring-8 ring-green-50">
                            <x-ui.icon name="check" class="h-8 w-8 stroke-[3]" />
                        </div>
                        <h4 class="mt-4 text-xl font-bold text-ink">Payment Received!</h4>
                        <p class="mt-1 text-sm text-ink/60">Your payment was confirmed. Redirecting to your order...</p>
                        <div class="mt-4 h-1.5 w-32 overflow-hidden rounded-full bg-cream">
                            <div class="h-full w-full animate-pulse bg-moss"></div>
                        </div>
                    </div>

                    {{-- Expired / Cancelled State --}}
                    <div x-show="chargeStatus === 'expire' || chargeStatus === 'cancel' || chargeStatus === 'deny' || chargeStatus === 'error'"
                        x-cloak class="flex flex-col items-center py-6 text-center">
                        <div class="flex h-16 w-16 items-center justify-center rounded-full bg-red-100 text-red-600 ring-8 ring-red-50">
                            <x-ui.icon name="alert-circle" class="h-8 w-8" />
                        </div>
                        <h4 class="mt-4 text-lg font-bold text-red-800"
                            x-text="chargeStatus === 'expire' ? 'Payment Expired' : (chargeStatus === 'cancel' ? 'Payment Cancelled' : 'Payment Error')"></h4>
                        <p class="mt-1 max-w-sm text-xs text-red-600"
                            x-text="errorMessage || (chargeStatus === 'expire' ? 'The payment time for this order has expired.' : 'Your payment could not be processed.')"></p>

                        <div class="mt-6 flex w-full flex-col gap-2 sm:flex-row">
                            <button type="button" @click="changeMethod()"
                                class="btn-primary w-full py-2.5 text-sm" x-show="remainingSeconds > 0">
                                Try Another Method
                            </button>
                            <a href="{{ route('track') }}" class="btn-outline w-full py-2.5 text-sm text-center">
                                Back to My Orders
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right column: Order Details & Countdown --}}
            <div class="space-y-6 lg:col-span-5 w-full lg:sticky lg:top-24">
                <div class="rounded-[2rem] bg-white p-6 shadow-sm ring-1 ring-blush/60 md:p-8">
                    <div class="flex items-center justify-between border-b border-blush/80 pb-4">
                        <div>
                            <span class="text-xs font-semibold uppercase tracking-wider text-moss">Order Number</span>
                            <div class="font-mono text-lg font-bold text-ink">{{ $transaction->order_number }}</div>
                        </div>
                        {{-- Live Countdown Pill --}}
                        <div class="flex items-center gap-1.5 rounded-full bg-petal/40 px-3 py-1 text-xs font-bold text-ink"
                            :class="remainingSeconds < 300 ? 'bg-red-100 text-red-700 animate-pulse' : 'bg-petal/40 text-ink'">
                            <x-ui.icon name="clock" class="h-3.5 w-3.5" />
                            <span x-text="formattedTime"></span>
                        </div>
                    </div>

                    {{-- Itemized list --}}
                    <div class="mt-5">
                        <span class="text-xs font-semibold uppercase tracking-wider text-ink/50">Items</span>
                        <ul class="mt-3 space-y-3 text-sm">
                            @foreach ($transaction->details as $detail)
                            <li class="flex justify-between gap-3">
                                <div>
                                    <span class="font-medium text-ink">{{ $detail->product->product_name }}</span>
                                    <span class="text-xs text-ink/60 block">{{ $detail->qty }} &times; {{ rupiah($detail->unit_price) }}</span>
                                </div>
                                <span class="shrink-0 font-semibold text-ink">{{ rupiah($detail->subtotal) }}</span>
                            </li>
                            @endforeach
                        </ul>
                    </div>

                    {{-- Summary lines --}}
                    <div class="mt-6 space-y-2 border-t border-blush/80 pt-5 text-sm">
                        <div class="flex justify-between text-ink/70">
                            <span>Subtotal</span>
                            <span>{{ rupiah($transaction->details->sum('subtotal')) }}</span>
                        </div>

                        @if ($transaction->isDelivery())
                        <div class="flex justify-between text-ink/70">
                            <span>Shipping ({{ strtoupper($transaction->courier) }} {{ $transaction->shipping_service }})</span>
                            <span>{{ rupiah($transaction->shipping_cost ?? 0) }}</span>
                        </div>
                        @else
                        <div class="flex justify-between text-ink/70">
                            <span>Fulfillment</span>
                            <span class="capitalize">Store Pickup</span>
                        </div>
                        @endif

                        <div class="flex items-baseline justify-between border-t border-blush/80 pt-3">
                            <span class="text-base font-bold text-ink">Total Payment</span>
                            <span class="text-2xl font-bold text-moss">{{ rupiah($transaction->total) }}</span>
                        </div>
                    </div>

                    <div class="mt-6 border-t border-blush/80 pt-4 text-center">
                        <a href="{{ route('track') }}" class="text-xs font-semibold text-ink/60 hover:text-moss hover:underline">
                            &larr; View all my orders
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-public-layout>
