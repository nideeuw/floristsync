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
            <div class="text-right">
                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-moss">Total</div>
                <div class="mt-1 text-2xl font-bold text-moss">{{ rupiah($selected->total) }}</div>
            </div>
        </div>
    </div>

    @if ($selected->status === 'cancelled')
    <div class="mt-6 rounded-2xl bg-red-100 px-4 py-3 text-sm text-red-800">This order was cancelled.</div>
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
