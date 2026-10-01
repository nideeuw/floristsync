<x-app-layout>
    <div class="mx-auto max-w-sm p-6 font-mono text-sm">
        <h1 class="text-center text-lg font-bold">FloristSync</h1>
        <p class="text-center">Order #{{ $transaction->id }}</p>
        <p class="text-center">{{ \Carbon\Carbon::parse($transaction->created_on)->format('d M Y H:i') }}</p>
        <p class="text-center">Cashier: {{ $transaction->cashier?->name ?? '-' }}</p>

        <hr class="my-3 border-dashed">

        @foreach ($transaction->details as $detail)
            <div class="mb-1">
                <div>{{ $detail->product->product_name }}</div>
                <div class="flex justify-between">
                    <span>{{ $detail->qty }} x {{ rupiah($detail->unit_price) }}</span>
                    <span>{{ rupiah($detail->subtotal) }}</span>
                </div>
            </div>
        @endforeach

        <hr class="my-3 border-dashed">

        <div class="flex justify-between font-bold">
            <span>Total</span>
            <span>{{ rupiah($transaction->total) }}</span>
        </div>
        <div class="flex justify-between">
            <span>Payment</span>
            <span>{{ strtoupper($transaction->payment?->payment_method ?? '-') }}</span>
        </div>

        <p class="mt-4 text-center">Thank you!</p>

        <button onclick="window.print()" class="mt-4 w-full rounded bg-black py-2 text-white print:hidden">
            Print
        </button>
    </div>
</x-app-layout>
