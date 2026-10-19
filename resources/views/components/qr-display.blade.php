@props([
    'status' => 'qris.status',
    'qrUrl' => 'qris.qr_url',
    'error' => 'qris?.error',
    'generatingText' => 'Generating QRIS code with Midtrans...',
    'waitingText' => 'Waiting for customer payment...',
    'hint' => 'Scan with GoPay, BCA, OVO, Dana, ShopeePay, or any Mobile Banking',
    'showError' => true,
])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center']) }}>
    {{-- Loading state --}}
    <div x-show="{{ $status }} === 'generating'" class="flex h-64 w-64 flex-col items-center justify-center rounded-2xl border border-blush bg-cream/30">
        <div class="h-10 w-10 animate-spin rounded-full border-4 border-moss border-t-transparent"></div>
        <p class="mt-3 text-xs text-gray-500">{{ $generatingText }}</p>
    </div>

    {{-- QR Code Display --}}
    <div x-show="{{ $status }} === 'pending'" class="flex flex-col items-center">
        <div class="relative rounded-2xl border-2 border-blush bg-white p-3 shadow-md">
            <template x-if="{{ $qrUrl }}">
                <img :src="{{ $qrUrl }}" alt="QRIS Code" class="h-60 w-60 object-contain">
            </template>
            <div class="mt-1 flex items-center justify-center gap-1 text-[11px] font-bold tracking-wider text-gray-700">
                <span class="rounded bg-red-600 px-1.5 py-0.5 text-white text-[10px]">QRIS</span>
                <span class="text-gray-500 text-[10px]">GPN / Midtrans</span>
            </div>
        </div>

        <div class="mt-3 flex items-center gap-2 text-xs text-gray-600">
            <span class="inline-block h-2 w-2 animate-ping rounded-full bg-moss"></span>
            <span>{{ $waitingText }}</span>
        </div>
        @if ($hint)
        <p class="mt-1 text-center text-[11px] text-gray-400 max-w-xs">{{ $hint }}</p>
        @endif
    </div>

    @if ($showError)
    {{-- Expired / Cancelled / Error State --}}
    <div x-show="{{ $status }} === 'expire' || {{ $status }} === 'cancel' || {{ $status }} === 'deny' || {{ $status }} === 'error'"
        class="flex h-64 w-full flex-col items-center justify-center rounded-2xl border border-red-200 bg-red-50/50 p-4">
        <div class="text-4xl">⚠️</div>
        <h3 class="mt-2 text-base font-bold text-red-800"
            x-text="{{ $status }} === 'expire' ? 'Payment Expired' : ({{ $status }} === 'cancel' ? 'Payment Cancelled' : 'Payment Error')"></h3>
        <p class="mt-1 text-xs text-red-600 text-center"
            x-text="{{ $error }} ?? 'The transaction was cancelled and items have been returned to stock.'"></p>
    </div>
    @endif
</div>
