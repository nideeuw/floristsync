@php
$isValid = $isValid ?? false;
$message = $message ?? 'Invoice is not available for this transaction.';
$storeName = $storeName ?? config('app.name', 'FloristSync');
$storeAddress = $storeAddress ?? config('floristsync.store_address', 'Jl. Merbabu No. 12, Malang');
$storePhone = $storePhone ?? config('floristsync.phone');
$storeWhatsapp = $storeWhatsapp ?? config('floristsync.whatsapp_number');

$itemsTotal = $transaction->details->sum('subtotal');
$shippingCost = $transaction->isDelivery() ? max(0, (int) $transaction->total - (int) $itemsTotal) : 0;
$payment = $transaction->payment;
$paymentMethod = $payment?->payment_method ? strtoupper(str_replace('_', ' ', $payment->payment_method)) : 'Midtrans Payment';
$paymentStatus = $payment?->payment_status ? ucfirst($payment->payment_status) : ucfirst(str_replace('_', ' ', $transaction->status));
$isDelivery = $transaction->isDelivery();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $isValid ? 'Invoice ' . $transaction->order_number : 'Invoice Unavailable' }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @media print {
            @page {
                size: A4 portrait;
                margin: 12mm 15mm;
            }
            html, body {
                background: #ffffff !important;
                color: #111827 !important;
                margin: 0 !important;
                padding: 0 !important;
                font-size: 11.5px !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .invoice-container {
                width: 100% !important;
                max-width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
                box-shadow: none !important;
                border: none !important;
                border-radius: 0 !important;
                background: #ffffff !important;
            }
            .no-print {
                display: none !important;
            }
            .avoid-break {
                break-inside: avoid !important;
                page-break-inside: avoid !important;
            }
            table {
                width: 100% !important;
            }
            tr {
                break-inside: avoid !important;
                page-break-inside: avoid !important;
            }
        }
    </style>
</head>

<body class="bg-cream/40 min-h-screen py-8 text-ink antialiased print:bg-white print:py-0 print:m-0">

    @if (! $isValid)
    {{-- Invoice Unavailable Card --}}
    <div class="mx-auto my-12 w-full max-w-md rounded-[2rem] bg-white p-8 shadow-sm ring-1 ring-blush/80 text-center font-sans">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-amber-100 text-3xl text-amber-700">
            ⚠️
        </div>
        <h1 class="mt-4 text-xl font-bold text-ink">Invoice Unavailable</h1>
        <p class="mt-2 text-sm text-ink/70 leading-relaxed">{{ $message }}</p>

        <div class="mt-6 rounded-2xl bg-cream/70 p-4 text-xs space-y-2 text-left">
            <div class="flex justify-between">
                <span class="text-ink/60">Order Number</span>
                <span class="font-mono font-bold text-ink">{{ $transaction->order_number }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-ink/60">Order Status</span>
                <span class="font-semibold uppercase {{ $transaction->status === 'cancelled' ? 'text-red-700' : 'text-amber-700' }}">
                    {{ str_replace('_', ' ', $transaction->status) }}
                </span>
            </div>
            @if ($transaction->payment)
            <div class="flex justify-between">
                <span class="text-ink/60">Payment Status</span>
                <span class="font-semibold uppercase text-ink/80">{{ $transaction->payment->payment_status }}</span>
            </div>
            @endif
            <div class="flex justify-between">
                <span class="text-ink/60">Date</span>
                <span class="text-ink">{{ $transaction->created_on?->format('d M Y, H:i') }}</span>
            </div>
        </div>

        <div class="mt-6 flex flex-col gap-2">
            <a href="{{ route('track', ['order' => $transaction->order_number]) }}" class="btn-primary w-full py-2.5 text-center text-sm font-semibold">
                Back to Order History
            </a>
            <a href="{{ route('home') }}" class="btn-outline w-full py-2.5 text-center text-sm font-semibold">
                Return to Home
            </a>
        </div>
    </div>

    @else
    {{-- Screen Action Bar --}}
    <div class="no-print mx-auto mb-6 flex max-w-3xl items-center justify-between px-4 sm:px-0">
        <a href="{{ route('track', ['order' => $transaction->order_number]) }}"
            class="btn-outline inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold">
            <span>&larr;</span>
            <span>Back to Order</span>
        </a>
        <div class="flex items-center gap-3">
            <button type="button" onclick="window.print()"
                class="btn-primary inline-flex items-center gap-2 px-5 py-2 text-xs font-semibold shadow-sm">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4H7v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                <span>Print Invoice</span>
            </button>
        </div>
    </div>

    {{-- Printable Invoice Document --}}
    <div class="invoice-container mx-auto max-w-3xl rounded-[2rem] bg-white p-8 shadow-sm ring-1 ring-blush/60 sm:p-12 print:max-w-full print:p-0 print:shadow-none print:ring-0">
        {{-- Header: Store Info & Invoice Title --}}
        <div class="flex flex-col justify-between gap-6 border-b border-blush/80 pb-8 sm:flex-row sm:items-start">
            <div class="space-y-1">
                <h1 class="text-2xl font-bold tracking-tight text-moss">{{ $storeName }}</h1>
                @if ($storeAddress)
                <p class="whitespace-pre-line text-xs text-ink/70 leading-relaxed">{{ $storeAddress }}</p>
                @endif
                <div class="pt-1 text-xs text-ink/70 space-y-0.5">
                    @if ($storePhone)
                    <div><span class="font-medium text-ink">Tel:</span> {{ $storePhone }}</div>
                    @endif
                    @if ($storeWhatsapp)
                    <div><span class="font-medium text-ink">WhatsApp:</span> {{ $storeWhatsapp }}</div>
                    @endif
                </div>
            </div>

            <div class="sm:text-right">
                <div class="inline-block rounded-full bg-sage/20 px-3 py-1 text-xs font-bold uppercase tracking-wider text-moss">
                    Official Invoice
                </div>
                <div class="mt-3 font-mono text-xl font-bold text-ink">{{ $transaction->order_number }}</div>
                <div class="mt-1 text-xs text-ink/60">
                    <div><span class="font-medium text-ink">Date:</span> {{ $transaction->created_on?->format('d M Y, H:i') }}</div>
                    <div class="mt-0.5">
                        <span class="font-medium text-ink">Status:</span>
                        <span class="font-semibold uppercase text-moss">{{ str_replace('_', ' ', $transaction->status) }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Customer & Fulfillment Grid --}}
        <div class="grid grid-cols-1 gap-6 border-b border-blush/80 py-6 sm:grid-cols-2">
            <div>
                <div class="text-[11px] font-bold uppercase tracking-wider text-moss">Billed To</div>
                <div class="mt-2 text-sm font-semibold text-ink">{{ $transaction->user?->name ?? 'Customer' }}</div>
                <div class="mt-1 text-xs text-ink/70 space-y-0.5">
                    @if ($transaction->user?->phone)
                    <div>{{ $transaction->user->phone }}</div>
                    @endif
                    @if ($transaction->user?->email)
                    <div>{{ $transaction->user->email }}</div>
                    @endif
                </div>
            </div>

            <div>
                <div class="text-[11px] font-bold uppercase tracking-wider text-moss">Fulfillment Method</div>
                <div class="mt-2 text-sm font-semibold text-ink">
                    {{ $isDelivery ? 'Delivery' : 'Store Pickup' }}
                    @if ($isDelivery && $transaction->courier)
                    <span class="text-ink/70 text-xs">({{ $transaction->shipping_service_label ?? strtoupper($transaction->courier) }})</span>
                    @endif
                </div>
                <div class="mt-1 text-xs text-ink/70 leading-relaxed">
                    @if ($isDelivery)
                        @if ($transaction->shipping_address)
                        <div class="whitespace-pre-line">{{ $transaction->shipping_address }}</div>
                        @endif
                        @if ($transaction->tracking_number)
                        <div class="mt-1 text-ink">
                            <span class="font-semibold">Waybill:</span>
                            <span class="font-mono">{{ $transaction->tracking_number }}</span>
                        </div>
                        @endif
                    @else
                        <div>Store location:</div>
                        <div class="whitespace-pre-line text-ink">{{ $storeAddress }}</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Order Items Table --}}
        <div class="py-6">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-blush text-ink/60 uppercase tracking-wider text-[11px]">
                        <th class="pb-3 font-semibold text-left">Item Description</th>
                        <th class="pb-3 font-semibold text-right w-24">Price</th>
                        <th class="pb-3 font-semibold text-center w-16">Qty</th>
                        <th class="pb-3 font-semibold text-right w-28">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-blush/50">
                    @foreach ($transaction->details as $detail)
                    <tr class="avoid-break">
                        <td class="py-3 pr-2">
                            <div class="font-medium text-ink">{{ $detail->product?->product_name ?? 'Product' }}</div>
                            @if ($detail->product?->category)
                            <div class="text-[10px] text-ink/50">{{ $detail->product->category->category_name }}</div>
                            @endif
                        </td>
                        <td class="py-3 text-right text-ink/80 font-mono">{{ rupiah($detail->unit_price) }}</td>
                        <td class="py-3 text-center text-ink/80 font-mono">{{ $detail->qty }}</td>
                        <td class="py-3 text-right font-medium text-ink font-mono">{{ rupiah($detail->subtotal) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Totals Summary --}}
        <div class="avoid-break border-t border-blush/80 pt-4">
            <div class="flex flex-col items-end">
                <div class="w-full max-w-xs space-y-1.5 text-xs">
                    <div class="flex justify-between text-ink/70">
                        <span>Items Subtotal</span>
                        <span class="font-mono font-medium text-ink">{{ rupiah($itemsTotal) }}</span>
                    </div>

                    <div class="flex justify-between text-ink/70">
                        <span>Shipping Cost</span>
                        <span class="font-mono font-medium text-ink">
                            @if ($isDelivery)
                            {{ $shippingCost > 0 ? rupiah($shippingCost) : rupiah(0) }}
                            @else
                            <span>Free (Pickup)</span>
                            @endif
                        </span>
                    </div>

                    <div class="border-t border-dashed border-blush/80 pt-2 flex justify-between text-sm font-bold text-moss">
                        <span>Grand Total</span>
                        <span class="font-mono text-base">{{ rupiah($transaction->total) }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Payment and Additional Information --}}
        <div class="avoid-break mt-6 rounded-2xl bg-cream/40 p-4 sm:p-5 text-xs text-ink/80 border border-blush/50">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <div class="font-bold uppercase tracking-wider text-[10px] text-moss">Payment Information</div>
                    <div class="mt-1.5 space-y-0.5">
                        <div class="flex gap-2">
                            <span class="text-ink/60">Method:</span>
                            <span class="font-semibold text-ink">{{ $paymentMethod }}</span>
                        </div>
                        <div class="flex gap-2">
                            <span class="text-ink/60">Status:</span>
                            <span class="font-semibold text-moss uppercase">{{ $paymentStatus }}</span>
                        </div>
                        @if ($payment?->payment_date)
                        <div class="flex gap-2">
                            <span class="text-ink/60">Paid on:</span>
                            <span>{{ $payment->payment_date->format('d M Y, H:i') }}</span>
                        </div>
                        @endif
                    </div>
                </div>

                @if ($transaction->notes)
                <div>
                    <div class="font-bold uppercase tracking-wider text-[10px] text-moss">Special Notes / Card Message</div>
                    <div class="mt-1.5 whitespace-pre-line italic text-ink/80 leading-relaxed">{{ $transaction->notes }}</div>
                </div>
                @endif
            </div>
        </div>

        {{-- Invoice Footer --}}
        <div class="avoid-break mt-8 pt-6 border-t border-blush/60 text-center text-xs text-ink/60">
            <p class="font-medium text-ink">Thank you for your purchase with {{ $storeName }}!</p>
            <p class="mt-1 text-[11px]">This document is a valid electronic proof of purchase.</p>
        </div>
    </div>

    {{-- Screen Bottom Action Bar --}}
    <div class="no-print mx-auto mt-6 flex max-w-3xl justify-center gap-3 px-4 sm:px-0">
        <button type="button" onclick="window.print()"
            class="btn-primary inline-flex items-center gap-2 px-6 py-2.5 text-xs font-semibold shadow-sm">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4H7v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
            </svg>
            <span>Print Invoice</span>
        </button>
        <a href="{{ route('track', ['order' => $transaction->order_number]) }}"
            class="btn-outline inline-flex items-center gap-1.5 px-5 py-2.5 text-xs font-semibold">
            <span>Back to Order History</span>
        </a>
    </div>

    @endif

</body>

</html>
