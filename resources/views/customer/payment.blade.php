<x-public-layout title="Payment">
    <script id="midtrans-script"
        type="text/javascript"
        src="https://api.midtrans.com/v2/assets/js/midtrans-new-3ds.min.js"
        data-environment="{{ ($isProduction ?? config('services.midtrans.is_production')) ? 'production' : 'sandbox' }}"
        data-client-key="{{ $clientKey }}">
    </script>

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
                    redirect_url: config.initialState.redirect_url,
                } : null,

                chargeStatus: (() => {
                    if (!config.initialState?.method) return 'idle';
                    if (config.initialState.payment_status === 'settlement' || config.initialState.payment_status === 'capture') return 'settlement';
                    if (config.initialState.payment_status === 'expire') return 'expire';
                    if (config.initialState.method === 'credit_card') {
                        if (config.initialState.payment_status === 'pending' && config.initialState.redirect_url) {
                            return 'pending';
                        }
                        return 'card_input';
                    }
                    return config.initialState.payment_status === 'pending' ? 'pending' : 'idle';
                })(),
                loading: false,
                errorMessage: '',

                // Card details state (stored ONLY in frontend memory, never submitted to our backend)
                cardNumber: '',
                cardRawNumber: '',
                cardHolder: config.customerName || '',
                cardExp: '',
                cardCvv: '',
                cardError: '',
                cardSubmitting: false,

                // 3-D Secure Modal state
                threeDsUrl: '',
                threeDsOpen: false,

                remainingSeconds: config.initialState?.remaining_seconds ?? 3600,
                timerInterval: null,
                pollInterval: null,

                init() {
                    this.startTimer();
                    if (this.activeCharge && this.chargeStatus === 'pending') {
                        this.startPolling();
                        if (this.method === 'credit_card' && this.activeCharge.redirect_url) {
                            this.handle3ds(this.activeCharge.redirect_url);
                        }
                    }
                },

                get formattedTime() {
                    if (this.remainingSeconds <= 0) return '00:00';
                    const m = Math.floor(this.remainingSeconds / 60);
                    const s = this.remainingSeconds % 60;
                    return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
                },

                get cardBrand() {
                    const clean = (this.cardNumber || '').replace(/\D/g, '');
                    if (/^4/.test(clean)) return 'visa';
                    if (/^(5[1-5]|2[2-7])/.test(clean)) return 'mastercard';
                    if (/^35/.test(clean)) return 'jcb';
                    if (/^3[47]/.test(clean)) return 'amex';
                    return 'unknown';
                },

                formatCardNumber(e) {
                    let v = e.target.value.replace(/\D/g, '').slice(0, 16);
                    this.cardRawNumber = v;
                    this.cardNumber = v.replace(/(\d{4})(?=\d)/g, '$1 ');
                },

                formatCardExpiry(e) {
                    let v = e.target.value.replace(/\D/g, '').slice(0, 4);
                    if (v.length >= 3) {
                        this.cardExp = v.slice(0, 2) + ' / ' + v.slice(2);
                    } else {
                        this.cardExp = v;
                    }
                },

                formatCardCvv(e) {
                    this.cardCvv = e.target.value.replace(/\D/g, '').slice(0, 4);
                },

                fillTestCard() {
                    this.cardRawNumber = '4811111111111114';
                    this.cardNumber = '4811 1111 1111 1114';
                    this.cardHolder = config.customerName || 'TEST USER';
                    this.cardExp = '12 / 28';
                    this.cardCvv = '123';
                    this.cardError = '';
                },

                retryCardInput() {
                    this.stopPolling();
                    this.threeDsOpen = false;
                    this.threeDsUrl = '';
                    this.cardSubmitting = false;
                    this.method = 'credit_card';
                    this.chargeStatus = 'card_input';
                    this.cardCvv = '';
                    if (this.errorMessage) {
                        this.cardError = this.errorMessage;
                    }
                    this.errorMessage = '';
                },

                validateCard() {
                    this.cardError = '';
                    const cleanNumber = (this.cardNumber || '').replace(/\s+/g, '');
                    if (cleanNumber.length < 15 || cleanNumber.length > 16) {
                        this.cardError = 'Please enter a valid 15 or 16-digit card number.';
                        return false;
                    }

                    const expParts = (this.cardExp || '').split('/');
                    const expMonth = parseInt(expParts[0]?.trim(), 10);
                    const expYearStr = expParts[1]?.trim() || '';
                    const expYear = expYearStr.length === 2 ? 2000 + parseInt(expYearStr, 10) : parseInt(expYearStr, 10);

                    if (!expMonth || expMonth < 1 || expMonth > 12) {
                        this.cardError = 'Please enter a valid expiry month (01-12).';
                        return false;
                    }

                    const now = new Date();
                    const curYear = now.getFullYear();
                    const curMonth = now.getMonth() + 1;
                    if (!expYear || expYear < curYear || (expYear === curYear && expMonth < curMonth)) {
                        this.cardError = 'Card expiration date cannot be in the past.';
                        return false;
                    }

                    if (!this.cardCvv || this.cardCvv.length < 3 || this.cardCvv.length > 4) {
                        this.cardError = 'Please enter a valid 3 or 4-digit CVV.';
                        return false;
                    }

                    return true;
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

                    if (m === 'card' || m === 'credit_card') {
                        this.method = 'credit_card';
                        this.chargeStatus = 'card_input';
                        this.errorMessage = '';
                        this.cardError = '';
                        return;
                    }

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
                        this.startPolling();
                    } catch (err) {
                        this.chargeStatus = 'error';
                        this.errorMessage = 'Network error. Please try again.';
                    } finally {
                        this.loading = false;
                    }
                },

                async submitCardPayment() {
                    if (this.cardSubmitting || this.remainingSeconds <= 0) return;
                    if (!this.validateCard()) return;

                    if (typeof MidtransNew3ds === 'undefined') {
                        this.cardError = 'Card security module is not loaded. Please refresh the page and try again.';
                        return;
                    }

                    this.cardSubmitting = true;
                    this.cardError = '';
                    this.errorMessage = '';

                    const cleanNumber = (this.cardNumber || '').replace(/\s+/g, '');
                    const expParts = (this.cardExp || '').split('/');
                    const expMonth = parseInt(expParts[0]?.trim(), 10);
                    const expYearStr = expParts[1]?.trim() || '';
                    const expYear = expYearStr.length === 2 ? '20' + expYearStr : expYearStr;

                    const cardData = {
                        card_number: cleanNumber,
                        card_exp_month: String(expMonth).padStart(2, '0'),
                        card_exp_year: String(expYear),
                        card_cvv: this.cardCvv,
                    };

                    MidtransNew3ds.getCardToken(cardData, {
                        onSuccess: async (res) => {
                            await this.chargeCardToken(res.token_id);
                        },
                        onFailure: (res) => {
                            this.cardSubmitting = false;
                            this.cardError = res.status_message || 'Could not verify card details. Please check your card number, expiry, and CVV.';
                        }
                    });
                },

                async chargeCardToken(tokenId) {
                    try {
                        const res = await fetch(`/api/customer/orders/${this.transactionId}/charge`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken,
                            },
                            body: JSON.stringify({
                                method: 'credit_card',
                                token_id: tokenId,
                            }),
                        });

                        const json = await res.json();
                        if (!res.ok) {
                            this.cardSubmitting = false;
                            this.cardError = json.message || 'Payment failed. Please try again.';
                            return;
                        }

                        this.activeCharge = json.data;
                        this.chargeStatus = 'pending';

                        if (json.data?.redirect_url) {
                            this.handle3ds(json.data.redirect_url);
                        } else {
                            this.cardSubmitting = false;
                            this.checkStatusNow();
                        }

                        this.startPolling();
                    } catch (err) {
                        this.cardSubmitting = false;
                        this.cardError = 'Network error while processing payment. Please try again.';
                    }
                },

                handle3ds(redirectUrl) {
                    if (!redirectUrl) {
                        this.checkStatusNow();
                        return;
                    }

                    if (typeof MidtransNew3ds === 'undefined') {
                        this.threeDsUrl = redirectUrl;
                        this.threeDsOpen = true;
                        return;
                    }

                    MidtransNew3ds.authenticate(redirectUrl, {
                        performAuthentication: (url) => {
                            this.threeDsUrl = url;
                            this.threeDsOpen = true;
                        },
                        onSuccess: () => {
                            this.threeDsOpen = false;
                            this.cardSubmitting = false;
                            this.checkStatusNow();
                        },
                        onFailure: () => {
                            this.threeDsOpen = false;
                            this.cardSubmitting = false;
                            this.checkStatusNow();
                        },
                        onPending: () => {
                            this.threeDsOpen = false;
                            this.cardSubmitting = false;
                            this.checkStatusNow();
                        },
                        onClose: () => {
                            this.threeDsOpen = false;
                            this.cardSubmitting = false;
                            this.checkStatusNow();
                        }
                    });
                },

                close3dsModal() {
                    this.threeDsOpen = false;
                    this.threeDsUrl = '';
                    this.cardSubmitting = false;
                    this.checkStatusNow();
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
                            if (data.message) {
                                this.errorMessage = data.message;
                            }
                        }
                    } catch (e) {
                        console.error('Polling status error', e);
                    }
                },

                async changeMethod() {
                    this.stopPolling();
                    this.loading = true;
                    this.errorMessage = '';
                    this.cardError = '';
                    this.threeDsOpen = false;
                    this.threeDsUrl = '';

                    // If user was only on card input screen and hadn't charged yet, no need to cancel on server
                    if (this.chargeStatus === 'card_input') {
                        this.method = '';
                        this.activeCharge = null;
                        this.chargeStatus = 'idle';
                        this.loading = false;
                        return;
                    }

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
            customerName: {{ \Illuminate\Support\Js::from(auth()->user()?->name ?? 'Cardholder') }},
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
                                        <span class="rounded bg-blue-100 px-1.5 py-0.5 text-[10px] font-bold text-blue-700">3-D Secure</span>
                                    </div>
                                    <p class="text-xs text-ink/60">Visa, Mastercard, JCB (direct bank-grade encryption)</p>
                                </div>
                            </div>
                            <x-ui.icon name="chevron-right" class="h-5 w-5 text-ink/30 transition group-hover:translate-x-0.5 group-hover:text-moss" />
                        </button>
                    </div>

                    <div x-show="errorMessage" x-cloak class="mt-4 rounded-xl bg-red-50 p-3 text-xs text-red-700">
                        <span x-text="errorMessage"></span>
                    </div>
                </div>

                {{-- Active Charge Screen (Card Input, Generating, Pending, Expired, Settlement) --}}
                <div x-show="chargeStatus !== 'idle'" x-cloak class="rounded-[2rem] bg-white p-6 shadow-sm ring-1 ring-blush/60 md:p-8">
                    {{-- Header with Change Method button --}}
                    <div class="flex items-center justify-between border-b border-blush/80 pb-4">
                        <div>
                            <span class="text-xs font-semibold uppercase tracking-wider text-moss">Payment method</span>
                            <h3 class="font-display text-xl font-bold capitalize text-ink"
                                x-text="method === 'credit_card' || method === 'card' ? 'Credit / Debit Card' : method"></h3>
                        </div>
                        <button type="button" @click="changeMethod()" :disabled="loading || cardSubmitting"
                            class="btn-outline inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold">
                            <x-ui.icon name="arrow-left" class="h-3.5 w-3.5" />
                            <span>Change method</span>
                        </button>
                    </div>

                    {{-- Loading / Generating state for non-card methods --}}
                    <div x-show="chargeStatus === 'generating'" class="flex h-64 flex-col items-center justify-center">
                        <div class="h-10 w-10 animate-spin rounded-full border-4 border-moss border-t-transparent"></div>
                        <p class="mt-4 text-sm font-medium text-ink/70">Connecting to Midtrans...</p>
                    </div>

                    {{-- Card Input Form (Custom On-Page Card Payment) --}}
                    <div x-show="chargeStatus === 'card_input'" class="mt-6 space-y-6">
                        {{-- Visual Card Preview --}}
                        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-tr from-slate-900 via-emerald-950 to-moss p-6 text-white shadow-xl">
                            {{-- Decorative light circle --}}
                            <div class="absolute -right-8 -top-8 h-36 w-36 rounded-full bg-white/10 blur-xl pointer-events-none"></div>

                            <div class="flex items-center justify-between">
                                {{-- Chip Icon --}}
                                <div class="flex h-9 w-12 items-center justify-center rounded-md bg-gradient-to-br from-amber-200 via-amber-400 to-amber-600 shadow-inner">
                                    <div class="h-6 w-9 rounded-sm border border-amber-800/40 opacity-80 grid grid-cols-3 gap-0.5 p-0.5">
                                        <div class="bg-amber-900/30 rounded-xs"></div>
                                        <div class="bg-amber-900/30 rounded-xs"></div>
                                        <div class="bg-amber-900/30 rounded-xs"></div>
                                    </div>
                                </div>

                                {{-- Brand Logo / Badge --}}
                                <div class="font-bold tracking-widest text-sm uppercase">
                                    <template x-if="cardBrand === 'visa'">
                                        <span class="rounded bg-white/20 px-2.5 py-1 text-xs font-extrabold tracking-wider text-white">VISA</span>
                                    </template>
                                    <template x-if="cardBrand === 'mastercard'">
                                        <div class="flex -space-x-2">
                                            <div class="h-6 w-6 rounded-full bg-red-500/90"></div>
                                            <div class="h-6 w-6 rounded-full bg-amber-400/90"></div>
                                        </div>
                                    </template>
                                    <template x-if="cardBrand === 'jcb'">
                                        <span class="rounded bg-blue-600 px-2.5 py-1 text-[11px] font-extrabold text-white">JCB</span>
                                    </template>
                                    <template x-if="cardBrand === 'amex'">
                                        <span class="rounded bg-sky-500 px-2.5 py-1 text-[11px] font-extrabold text-white">AMEX</span>
                                    </template>
                                    <template x-if="cardBrand === 'unknown'">
                                        <span class="text-xs text-white/50 font-medium">DEBIT / CREDIT</span>
                                    </template>
                                </div>
                            </div>

                            {{-- Card Number Display --}}
                            <div class="mt-6 font-mono text-lg tracking-widest sm:text-xl"
                                x-text="cardNumber || '•••• •••• •••• ••••'">
                            </div>

                            <div class="mt-5 flex items-end justify-between text-xs">
                                <div>
                                    <span class="text-[9px] uppercase tracking-wider text-white/60 block">Cardholder</span>
                                    <span class="font-medium tracking-wide uppercase text-white/90"
                                        x-text="cardHolder || 'CUSTOMER NAME'"></span>
                                </div>
                                <div class="text-right">
                                    <span class="text-[9px] uppercase tracking-wider text-white/60 block">Expires</span>
                                    <span class="font-mono font-medium text-white/90"
                                        x-text="cardExp || 'MM/YY'"></span>
                                </div>
                            </div>
                        </div>

                        {{-- Sandbox Test Card Helper (shown only in sandbox / non-production mode) --}}
                        @if (!($isProduction ?? config('services.midtrans.is_production')))
                        <div class="rounded-2xl border border-amber-300 bg-amber-50/80 p-4 shadow-sm">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <div>
                                    <div class="flex items-center gap-1.5 font-bold text-xs uppercase tracking-wider text-amber-900">
                                        <x-ui.icon name="info" class="h-4 w-4 text-amber-700" />
                                        <span>Midtrans Sandbox Test Card</span>
                                    </div>
                                    <p class="mt-1 font-mono text-xs text-amber-800">
                                        Card: <strong class="select-all">4811 1111 1111 1114</strong> &bull; CVV: <strong class="select-all">123</strong> &bull; OTP: <strong class="select-all">112233</strong>
                                    </p>
                                </div>
                                <button type="button" @click="fillTestCard()"
                                    class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-amber-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500">
                                    <x-ui.icon name="sparkles" class="h-3.5 w-3.5" />
                                    <span>Auto-Fill Test Card</span>
                                </button>
                            </div>
                        </div>
                        @endif

                        {{-- Card Details Input Fields (NO name attributes to ensure PCI compliance) --}}
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-ink">Card Number</label>
                                <div class="relative mt-1">
                                    <input type="text"
                                        inputmode="numeric"
                                        autocomplete="cc-number"
                                        placeholder="4000 0000 0000 0000"
                                        maxlength="19"
                                        :value="cardNumber"
                                        @input="formatCardNumber($event)"
                                        class="w-full rounded-xl border border-blush/80 px-3.5 py-2.5 text-sm font-mono text-ink placeholder:text-ink/30 focus:border-moss focus:outline-none focus:ring-1 focus:ring-moss">
                                    <div class="absolute right-3 top-1/2 -translate-y-1/2">
                                        <x-ui.icon name="credit-card" class="h-5 w-5 text-ink/30" />
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-ink">Cardholder Name</label>
                                <input type="text"
                                    autocomplete="cc-name"
                                    placeholder="Name on card"
                                    x-model="cardHolder"
                                    class="mt-1 w-full rounded-xl border border-blush/80 px-3.5 py-2.5 text-sm uppercase text-ink placeholder:text-ink/30 focus:border-moss focus:outline-none focus:ring-1 focus:ring-moss">
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-ink">Expiration Date</label>
                                    <input type="text"
                                        inputmode="numeric"
                                        autocomplete="cc-exp"
                                        placeholder="MM / YY"
                                        maxlength="7"
                                        :value="cardExp"
                                        @input="formatCardExpiry($event)"
                                        class="mt-1 w-full rounded-xl border border-blush/80 px-3.5 py-2.5 text-sm font-mono text-ink placeholder:text-ink/30 focus:border-moss focus:outline-none focus:ring-1 focus:ring-moss">
                                </div>
                                <div>
                                    <div class="flex items-center justify-between">
                                        <label class="block text-xs font-bold text-ink">CVV / CVC</label>
                                        <span class="text-[10px] text-ink/40">3-4 digits on back</span>
                                    </div>
                                    <div class="relative mt-1">
                                        <input type="text"
                                            inputmode="numeric"
                                            autocomplete="cc-csc"
                                            placeholder="123"
                                            maxlength="4"
                                            :value="cardCvv"
                                            @input="formatCardCvv($event)"
                                            class="w-full rounded-xl border border-blush/80 px-3.5 py-2.5 text-sm font-mono text-ink placeholder:text-ink/30 focus:border-moss focus:outline-none focus:ring-1 focus:ring-moss">
                                        <div class="absolute right-3 top-1/2 -translate-y-1/2">
                                            <x-ui.icon name="lock" class="h-4 w-4 text-ink/30" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Security Notice --}}
                        <div class="flex items-start gap-2.5 rounded-xl bg-petal/20 p-3 text-[11px] text-ink/70">
                            <x-ui.icon name="shield-check" class="mt-0.5 h-4 w-4 shrink-0 text-moss" />
                            <div>
                                <span class="font-bold text-ink">Bank-Grade 3-D Secure Protection:</span>
                                <span> Your sensitive card data is encrypted and transmitted directly from your browser to Midtrans (PCI-DSS Level 1 certified). FloristSync never stores your card details.</span>
                            </div>
                        </div>

                        {{-- Error Alert --}}
                        <div x-show="cardError" x-cloak class="rounded-xl bg-red-50 p-3 text-xs text-red-700">
                            <span x-text="cardError"></span>
                        </div>

                        {{-- Pay Button --}}
                        <button type="button" @click="submitCardPayment()" :disabled="cardSubmitting || remainingSeconds <= 0"
                            class="btn-primary w-full py-3.5 text-base font-semibold shadow-md flex items-center justify-center gap-2">
                            <template x-if="!cardSubmitting">
                                <span class="flex items-center gap-2">
                                    <x-ui.icon name="lock" class="h-4 w-4" />
                                    <span>Pay {{ rupiah($transaction->total) }}</span>
                                </span>
                            </template>
                            <template x-if="cardSubmitting">
                                <span class="flex items-center gap-2">
                                    <div class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></div>
                                    <span>Securing Card Payment...</span>
                                </span>
                            </template>
                        </button>
                    </div>

                    {{-- Pending Active Method Display (QRIS, GoPay, ShopeePay, or 3DS In Progress) --}}
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

                        {{-- Credit Card View: 3DS Verification In Progress --}}
                        <div x-show="method === 'credit_card' || method === 'card'" class="flex flex-col items-center text-center py-4">
                            <div class="flex h-20 w-20 items-center justify-center rounded-3xl bg-blue-50 text-blue-600 shadow-sm ring-1 ring-blue-200">
                                <x-ui.icon name="shield-check" class="h-10 w-10 text-blue-600" />
                            </div>
                            <h4 class="mt-4 text-lg font-bold text-ink">Bank Authentication in Progress</h4>
                            <p class="mt-1 max-w-sm text-xs text-ink/60">
                                Complete the 3-D Secure verification with your bank. If the verification window was closed, tap below to resume.
                            </p>

                            <template x-if="activeCharge && activeCharge.redirect_url">
                                <div class="mt-5 w-full max-w-sm space-y-2">
                                    <button type="button" @click="handle3ds(activeCharge.redirect_url)"
                                        class="btn-primary inline-flex w-full items-center justify-center gap-2 py-3 text-sm font-semibold shadow-md">
                                        <x-ui.icon name="lock" class="h-4 w-4" />
                                        <span>Complete 3-D Secure Verification</span>
                                    </button>
                                    <button type="button" @click="chargeStatus = 'card_input'; cardSubmitting = false;"
                                        class="btn-outline inline-flex w-full items-center justify-center gap-1.5 py-2 text-xs font-semibold">
                                        <span>Use a different card</span>
                                    </button>
                                </div>
                            </template>

                            <div class="mt-5 flex items-center gap-2 text-xs text-gray-600">
                                <span class="inline-block h-2 w-2 animate-ping rounded-full bg-moss"></span>
                                <span>Waiting for bank confirmation...</span>
                            </div>
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
                            x-text="chargeStatus === 'expire' ? 'Payment Expired' : (chargeStatus === 'cancel' ? 'Payment Cancelled' : (chargeStatus === 'deny' ? 'Payment Declined by Bank' : 'Payment Error'))"></h4>
                        <p class="mt-1 max-w-sm text-xs text-red-600"
                            x-text="errorMessage || (chargeStatus === 'expire' ? 'The payment time for this order has expired.' : 'Your payment could not be processed.')"></p>

                        <div class="mt-6 flex w-full flex-col gap-2 sm:flex-row">
                            <template x-if="(method === 'credit_card' || method === 'card') && chargeStatus !== 'expire' && remainingSeconds > 0">
                                <button type="button" @click="retryCardInput()"
                                    class="btn-primary w-full py-2.5 text-sm">
                                    Re-enter Card / CVV
                                </button>
                            </template>
                            <button type="button" @click="changeMethod()"
                                class="w-full py-2.5 text-sm"
                                :class="(method === 'credit_card' || method === 'card') && chargeStatus !== 'expire' ? 'btn-outline' : 'btn-primary'"
                                x-show="remainingSeconds > 0">
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
                            <span>Shipping ({{ $transaction->shipping_service_label ?? (strtoupper($transaction->courier) . ($transaction->shipping_service ? ' ' . $transaction->shipping_service : '')) }})</span>
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

        {{-- 3-D Secure Authentication Modal --}}
        <div x-show="threeDsOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
            {{-- Backdrop --}}
            <div class="fixed inset-0 bg-ink/60 backdrop-blur-sm transition-opacity"
                @click="close3dsModal()"></div>

            {{-- Modal Window --}}
            <div class="relative w-full max-w-xl overflow-hidden rounded-3xl bg-white shadow-2xl ring-1 ring-ink/10">
                {{-- Header --}}
                <div class="flex items-center justify-between border-b border-blush/80 px-6 py-4 bg-cream/40">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 text-blue-700">
                            <x-ui.icon name="lock" class="h-4 w-4" />
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-ink">Bank Authentication (3-D Secure)</h3>
                            <p class="text-[11px] text-ink/60">Complete security verification with your card issuer</p>
                        </div>
                    </div>
                    <button type="button" @click="close3dsModal()"
                        class="rounded-xl p-1.5 text-ink/40 hover:bg-petal/30 hover:text-ink">
                        <x-ui.icon name="x" class="h-5 w-5" />
                    </button>
                </div>

                {{-- iFrame Content Container --}}
                <div class="relative bg-white" style="height: 500px;">
                    <iframe :src="threeDsUrl"
                        class="h-full w-full border-0"
                        allow="payment"
                        sandbox="allow-forms allow-scripts allow-same-origin allow-top-navigation allow-modals">
                    </iframe>
                </div>

                {{-- Footer --}}
                <div class="border-t border-blush/80 px-6 py-3 bg-cream/20 flex items-center justify-between text-xs text-ink/60">
                    <span>Enter OTP or verification code sent by your bank.</span>
                    <button type="button" @click="close3dsModal()"
                        class="font-semibold text-moss hover:underline">
                        Cancel & Check Status
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-public-layout>
