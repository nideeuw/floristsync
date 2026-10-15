@php
$isValid = $isValid ?? false;
$message = $message ?? 'Receipt is not available for this transaction.';
$isCash = $transaction->payment?->payment_method === 'cash';
$paid = request()->has('paid') ? (int) request('paid') : (int) $transaction->total;
$change = $isCash ? max(0, $paid - $transaction->total) : null;
$storeName = $storeName ?? config('app.name', 'FloristSync');
$storeAddress = $storeAddress ?? null;
$storePhone = $storePhone ?? null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $isValid ? 'Receipt ' . $transaction->order_number : 'Receipt Unavailable' }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @media print {
            @page {
                size: 80mm auto;
                margin: 0;
            }
            html, body {
                width: 80mm !important;
                margin: 0 !important;
                padding: 4mm !important;
                background: #ffffff !important;
                color: #000000 !important;
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace !important;
                font-size: 11px !important;
            }
            .receipt-container {
                width: 100% !important;
                max-width: 100% !important;
                padding: 0 !important;
                box-shadow: none !important;
                border: none !important;
                background: #ffffff !important;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>

<body class="bg-cream py-6 font-mono text-xs text-ink print:bg-white print:py-0 print:m-0">

    @if (! $isValid)
    {{-- Message for pending or cancelled sales --}}
    <div class="mx-auto w-80 max-w-[80mm] rounded-2xl bg-white p-6 shadow-md border border-blush text-center font-sans">
        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-amber-100 text-2xl text-amber-700">⚠️</div>
        <h1 class="mt-3 text-base font-bold text-ink">Receipt Unavailable</h1>
        <p class="mt-2 text-xs text-gray-600 leading-relaxed">{{ $message }}</p>

        <div class="mt-4 rounded-xl bg-cream/70 p-3 text-xs space-y-1">
            <div class="flex justify-between text-gray-500">
                <span>Order</span>
                <span class="font-mono font-bold text-ink">{{ $transaction->order_number }}</span>
            </div>
            <div class="flex justify-between text-gray-500">
                <span>Status</span>
                <span class="font-bold uppercase text-amber-700">{{ str_replace('_', ' ', $transaction->status) }}</span>
            </div>
            @if ($transaction->payment)
            <div class="flex justify-between text-gray-500">
                <span>Payment</span>
                <span class="font-bold uppercase text-gray-700">{{ $transaction->payment->payment_status }}</span>
            </div>
            @endif
        </div>

        <div class="mt-5 flex flex-col gap-2">
            <a href="{{ route('cashier.pos') }}" class="btn-primary w-full py-2 text-center text-xs">Back to POS</a>
            <a href="{{ route('cashier.history') }}" class="btn-outline w-full py-2 text-center text-xs">Sales History</a>
        </div>
    </div>

    @else
    {{-- Valid thermal receipt --}}
    <div class="receipt-container mx-auto w-80 max-w-[80mm] bg-white p-5 shadow print:w-full print:max-w-full print:p-0 print:shadow-none">
        {{-- Store info from settings --}}
        <div class="text-center">
            <div class="text-base font-bold tracking-tight text-ink">{{ $storeName }}</div>
            @if ($storeAddress)
            <div class="mt-0.5 text-[11px] text-gray-600 leading-tight whitespace-pre-line">{{ $storeAddress }}</div>
            @endif
            @if ($storePhone)
            <div class="text-[11px] text-gray-500">Tel: {{ $storePhone }}</div>
            @endif
        </div>

        <hr class="my-3 border-dashed border-gray-400">

        {{-- Transaction Metadata --}}
        <div class="space-y-0.5 text-xs">
            <div class="flex justify-between"><span>Order</span><span class="font-bold">{{ $transaction->order_number }}</span></div>
            <div class="flex justify-between"><span>Date</span><span>{{ \Carbon\Carbon::parse($transaction->created_on)->format('d/m/Y H:i') }}</span></div>
            <div class="flex justify-between"><span>Cashier</span><span>{{ $transaction->cashier?->name ?? '-' }}</span></div>
            @if ($transaction->user)
            <div class="flex justify-between"><span>Member</span><span class="font-semibold">{{ $transaction->user->name }}</span></div>
            @endif
        </div>

        <hr class="my-3 border-dashed border-gray-400">

        {{-- Order Items --}}
        <div class="space-y-1.5">
            @foreach ($transaction->details as $detail)
            <div>
                <div class="font-semibold leading-snug">{{ $detail->product?->product_name ?? 'Product' }}</div>
                <div class="flex justify-between text-gray-600">
                    <span>{{ $detail->qty }} x {{ rupiah($detail->unit_price) }}</span>
                    <span>{{ rupiah($detail->subtotal) }}</span>
                </div>
            </div>
            @endforeach
        </div>

        <hr class="my-3 border-dashed border-gray-400">

        {{-- Payment and Totals --}}
        <div class="space-y-0.5 text-xs">
            <div class="flex justify-between text-sm font-extrabold">
                <span>TOTAL</span>
                <span>{{ rupiah($transaction->total) }}</span>
            </div>
            <div class="flex justify-between">
                <span>Payment</span>
                <span class="uppercase font-semibold">{{ strtoupper($transaction->payment?->payment_method ?? '-') }}</span>
            </div>

            @if ($isCash)
            <div class="flex justify-between"><span>Cash</span><span>{{ rupiah($paid) }}</span></div>
            <div class="flex justify-between font-bold"><span>Change</span><span>{{ rupiah($change) }}</span></div>
            @endif
        </div>

        {{-- Special Note / Card Message --}}
        @if ($transaction->notes)
        <hr class="my-3 border-dashed border-gray-400">
        <div class="text-[11px]">
            <div class="font-bold text-gray-700">Special Note:</div>
            <div class="mt-0.5 whitespace-pre-line text-gray-800 italic leading-relaxed">{{ $transaction->notes }}</div>
        </div>
        @endif

        <hr class="my-3 border-dashed border-gray-400">

        <p class="text-center text-[11px] text-gray-600">Thank you for shopping!</p>
    </div>

    {{-- Screen-only action buttons --}}
    <div class="no-print mx-auto mt-4 flex w-80 max-w-[80mm] gap-2 print:hidden">
        <button type="button" onclick="window.print()" class="btn-primary flex-1 py-2 text-center text-xs font-semibold">Print Receipt</button>
        <a href="{{ route('cashier.pos') }}" class="btn-outline flex-1 py-2 text-center text-xs font-semibold">Back to POS</a>
    </div>
    @endif

</body>

</html>
