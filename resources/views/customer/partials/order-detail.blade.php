@php
    $statusIndex = [
        'pending_payment' => 0,
        'processing' => 1,
        'shipped' => 2,
        'ready_for_pickup' => 2,
        'completed' => 3,
    ];

    $steps = [
        'Waiting for payment',
        'Preparing your order',
        $selected->isDelivery() ? 'Shipped' : 'Ready for pickup',
        'Completed',
    ];
    $current = $statusIndex[$selected->status] ?? 0;
    $manifest = is_array($tracking) ? ($tracking['manifest'] ?? []) : [];
@endphp

<div class="rounded-[2rem] bg-white p-6 shadow-sm ring-1 ring-blush/60 md:p-8">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <div class="text-xs font-semibold uppercase tracking-[0.2em] text-moss">Order number</div>
            <div class="mt-1 font-mono text-lg font-semibold">{{ $selected->order_number }}</div>
        </div>
        <div class="flex flex-wrap items-center gap-4">
            <a href="{{ route('track.invoice', $selected) }}" target="_blank"
                class="btn-outline inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold">
                <x-ui.icon name="printer" class="h-4 w-4" />
                <span>Print invoice</span>
            </a>
            @if (in_array($selected->status, ['processing', 'ready_for_pickup', 'shipped', 'completed'], true))
            <form method="POST" action="{{ route('track.invoice.resend', $selected->order_number) }}">
                @csrf
                <button class="btn-outline inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold">Resend invoice</button>
            </form>
            @endif
            <div class="text-right">
                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-moss">Total</div>
                <div class="mt-1 text-2xl font-bold text-moss">{{ rupiah($selected->total) }}</div>
            </div>
        </div>
    </div>

    @if ($selected->status === 'cancelled')
    <div class="mt-6 rounded-2xl bg-red-100 px-4 py-3 text-sm text-red-800">This order was cancelled.</div>

    @php($refundPayment = $selected->payment)
    @if ($refundPayment?->refund_status === 'success')
    <div class="mt-3 rounded-2xl bg-cream px-4 py-3 text-sm">
        Refunded <span class="font-semibold">{{ rupiah($refundPayment->refund_amount ?? $refundPayment->amount) }}</span>
        on {{ $refundPayment->refunded_at?->format('d M Y, H:i') }}
    </div>
    @elseif (in_array($refundPayment?->refund_status, ['pending', 'failed'], true))
    <div class="mt-3 rounded-2xl bg-cream px-4 py-3 text-sm">
        Your refund is being processed. If it takes longer than expected, please contact us via WhatsApp.
    </div>
    @endif
    @else
    <ol class="mt-8">
        @foreach ($steps as $index => $label)
        <li class="relative flex items-center gap-4 {{ $loop->last ? '' : 'pb-7' }}">
            @unless ($loop->last)
            <span class="absolute left-4 top-8 h-full w-0.5 -translate-x-1/2 {{ $index < $current ? 'bg-moss' : 'bg-blush' }}"></span>
            @endunless
            <span class="relative flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold
                        {{ $index <= $current ? 'bg-moss text-white' : 'bg-blush text-ink/50' }}
                        {{ $index === $current ? 'ring-4 ring-sage/40' : '' }}">
                @if ($index <= $current)
                <x-ui.icon name="check" class="h-4 w-4" />
                @else
                {{ $index + 1 }}
                @endif
            </span>
            <span class="{{ $index === $current ? 'font-semibold' : ($index < $current ? '' : 'text-ink/40') }}">
                {{ $label }}
            </span>
        </li>
        @endforeach
    </ol>
    @endif

    @if ($selected->isDelivery())
    <div class="mt-6 rounded-2xl bg-cream px-4 py-3 text-sm">
        Courier: <span class="font-semibold">{{ $selected->shipping_service_label ?? strtoupper($selected->courier) }}</span>
        @if ($selected->tracking_number)
        &middot; Tracking number: <span class="font-mono font-semibold">{{ $selected->tracking_number }}</span>
        @endif
    </div>

    @if ($selected->tracking_number && count($manifest))
    <ul class="mt-3 space-y-2 text-sm">
        @foreach ($manifest as $row)
        <li class="rounded-2xl border border-blush px-4 py-2.5">
            <div class="text-xs text-ink/50">
                {{ data_get($row, 'manifest_date') }} {{ data_get($row, 'manifest_time') }}
            </div>
            <div>{{ data_get($row, 'manifest_description') }}</div>
        </li>
        @endforeach
    </ul>
    @endif
    @endif

    @if ($selected->status === 'shipped' && $selected->isDelivery())
    @php($autoDays = (int) config('floristsync.auto_complete_shipped_days'))
    <div x-data="{ open: false, busy: false }"
        @keydown.escape.window="open = false"
        x-effect="document.body.classList.toggle('overflow-hidden', open)"
        class="mt-6 flex flex-col gap-3 rounded-2xl bg-sage/20 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm">
            Your parcel arrived?
            @if ($autoDays > 0)
            <span class="text-ink/60">If you do nothing, the order completes by itself {{ $autoDays }} days after it was shipped.</span>
            @endif
        </p>
        <button type="button" @click="open = true" class="btn-primary shrink-0 px-5 py-2.5 text-sm">Order received</button>

        <template x-teleport="body">
            <div x-show="open" style="display: none"
                class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
                role="dialog" aria-modal="true" aria-labelledby="received-title">
                <div x-show="open" x-transition.opacity.duration.200ms
                    class="absolute inset-0 bg-ink/40 backdrop-blur-sm" @click="open = false"></div>

                <div x-show="open"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="translate-y-4 opacity-0 sm:translate-y-0 sm:scale-95"
                    x-transition:enter-end="translate-y-0 opacity-100 sm:scale-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="relative w-full max-w-md rounded-[2rem] bg-white p-6 text-center shadow-xl ring-1 ring-blush/60 sm:p-8">
                    <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-sage/30 text-moss">
                        <x-ui.icon name="check" class="h-7 w-7" />
                    </span>

                    <h2 id="received-title" class="mt-4 text-xl font-semibold">Has your order arrived?</h2>
                    <p class="mt-2 text-sm text-ink/70">
                        Order <span class="font-mono font-semibold">{{ $selected->order_number }}</span> will be marked as completed.
                        Please confirm only if you have received your flowers.
                    </p>

                    <form method="POST" action="{{ route('track.received', $selected->order_number) }}"
                        @submit="busy = true"
                        class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-center">
                        @csrf
                        <button type="button" @click="open = false"
                            class="btn-outline px-5 py-2.5 text-sm font-semibold">Not yet</button>
                        <button :disabled="busy"
                            class="btn-primary px-5 py-2.5 text-sm disabled:cursor-not-allowed disabled:opacity-60">
                            <span x-show="!busy">Yes, it arrived</span>
                            <span x-show="busy" style="display: none">Confirming...</span>
                        </button>
                    </form>
                </div>
            </div>
        </template>
    </div>
    @endif

    <div class="mt-6 border-t border-blush/80 pt-5">
        <div class="mb-3 text-sm font-semibold">Items</div>
        <ul class="space-y-2 text-sm">
            @foreach ($selected->details as $detail)
            <li class="flex justify-between gap-3">
                <span>{{ $detail->qty }} x {{ $detail->product->product_name }}</span>
                <span class="shrink-0 font-medium">{{ rupiah($detail->subtotal) }}</span>
            </li>
            @endforeach
        </ul>
    </div>

    <div class="mt-5 text-right">
        <a href="{{ route('track', array_filter(['page' => request()->query('page')])) }}"
            class="text-sm font-semibold text-moss hover:underline">Close</a>
    </div>
</div>
