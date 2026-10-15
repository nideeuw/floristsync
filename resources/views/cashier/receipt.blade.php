@php
$paid = (int) request('paid');
$isCash = $transaction->payment?->payment_method === 'cash';
$change = ($isCash && $paid >= $transaction->total) ? $paid - $transaction->total : null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt {{ $transaction->order_number }}</title>
    @vite(['resources/css/app.css'])
</head>

<body class="bg-cream py-6 font-mono text-xs text-ink print:bg-white print:py-0">

    <div class="mx-auto w-80 bg-white p-5 shadow print:w-full print:shadow-none">
        <div class="text-center">
            <div class="text-base font-bold">FloristSync</div>
            <div class="text-gray-500">Fresh flowers for every moment</div>
        </div>

        <hr class="my-3 border-dashed border-gray-400">

        <div class="space-y-0.5">
            <div class="flex justify-between"><span>Order</span><span>{{ $transaction->order_number }}</span></div>
            <div class="flex justify-between"><span>Date</span><span>{{ \Carbon\Carbon::parse($transaction->created_on)->format('d/m/Y H:i') }}</span></div>
            <div class="flex justify-between"><span>Cashier</span><span>{{ $transaction->cashier?->name ?? '-' }}</span></div>
            @if ($transaction->user)
            <div class="flex justify-between"><span>Customer</span><span>{{ $transaction->user->name }}</span></div>
            @endif
        </div>

        <hr class="my-3 border-dashed border-gray-400">

        @foreach ($transaction->details as $detail)
        <div class="mb-1.5">
            <div>{{ $detail->product->product_name }}</div>
            <div class="flex justify-between text-gray-600">
                <span>{{ $detail->qty }} x {{ rupiah($detail->unit_price) }}</span>
                <span>{{ rupiah($detail->subtotal) }}</span>
            </div>
        </div>
        @endforeach

        <hr class="my-3 border-dashed border-gray-400">

        <div class="space-y-0.5">
            <div class="flex justify-between text-sm font-bold"><span>TOTAL</span><span>{{ rupiah($transaction->total) }}</span></div>
            <div class="flex justify-between"><span>Payment</span><span>{{ strtoupper($transaction->payment?->payment_method ?? '-') }}</span></div>

            @if ($change !== null)
            <div class="flex justify-between"><span>Cash</span><span>{{ rupiah($paid) }}</span></div>
            <div class="flex justify-between"><span>Change</span><span>{{ rupiah($change) }}</span></div>
            @endif
        </div>

        @if ($transaction->notes)
        <hr class="my-3 border-dashed border-gray-400">
        <div class="text-xs">
            <div class="font-bold">Card Message / Note:</div>
            <div class="whitespace-pre-line text-gray-700">{{ $transaction->notes }}</div>
        </div>
        @endif

        <hr class="my-3 border-dashed border-gray-400">

        <p class="text-center">Thank you for shopping!</p>
    </div>

    <div class="mx-auto mt-4 flex w-80 gap-2 print:hidden">
        <button onclick="window.print()" class="btn-primary flex-1">Print</button>
        <a href="{{ route('cashier.pos') }}" class="btn-outline flex-1">Back to POS</a>
    </div>
</body>

</html>
