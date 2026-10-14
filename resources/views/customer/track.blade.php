<x-public-layout title="Order History">
    @php
        $statusIndex = [
            'pending_payment' => 0,
            'processing' => 1,
            'shipped' => 2,
            'ready_for_pickup' => 2,
            'completed' => 3,
        ];
        $cancellable = [\App\Models\Transaction::PENDING_PAYMENT, \App\Models\Transaction::PROCESSING];
    @endphp

    <div class="mx-auto max-w-3xl px-4 py-8 md:py-12">
        <x-page-header eyebrow="My orders" title="Order history"
            description="Your orders, newest first. Pick an order to see its status." />

        {{-- Selected order: status timeline --}}
        @if ($orderNumber !== '' && ! $selected)
        <x-ui.empty-state icon="search-x" title="Order not found"
            description="We could not find that order in your account." class="mt-8" />
        @endif

        @if ($selected)
        @php
            $steps = [
                'Waiting for payment',
                'Preparing your order',
                $selected->isDelivery() ? 'Shipped' : 'Ready for pickup',
                'Completed',
            ];
            $current = $statusIndex[$selected->status] ?? 0;
            $manifest = is_array($tracking) ? ($tracking['manifest'] ?? []) : [];
        @endphp

        <div class="mt-8 rounded-[2rem] bg-white p-6 shadow-sm ring-1 ring-blush/60 md:p-8">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.2em] text-moss">Order number</div>
                    <div class="mt-1 font-mono text-lg font-semibold">{{ $selected->order_number }}</div>
                </div>
                <div class="text-right">
                    <div class="text-xs font-semibold uppercase tracking-[0.2em] text-moss">Total</div>
                    <div class="mt-1 text-2xl font-bold text-moss">{{ rupiah($selected->total) }}</div>
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

            @if ($selected->isDelivery() && $selected->tracking_number)
            <div class="mt-6 rounded-2xl bg-cream px-4 py-3 text-sm">
                Courier: <span class="font-semibold uppercase">{{ $selected->courier }}</span> &middot;
                Tracking number: <span class="font-mono font-semibold">{{ $selected->tracking_number }}</span>
            </div>

            @if (count($manifest))
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
        </div>
        @endif

        {{-- Own orders --}}
        <div class="mt-8 space-y-4">
            @forelse ($orders as $order)
            @php
                $canCancel = in_array($order->status, $cancellable, true);
                $cancelState = $order->cancel_request_status;
                $reasonFailed = $errors->has('reason') && (int) old('cancel_order_id') === (int) $order->id;
            @endphp
            <div x-data="{ open: {{ $reasonFailed ? 'true' : 'false' }} }"
                class="flex flex-wrap items-center justify-between gap-4 rounded-[2rem] bg-white p-5 shadow-sm ring-1 transition hover:shadow-md
                        {{ $selected && $selected->id === $order->id ? 'ring-2 ring-sage' : 'ring-blush/60' }}">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-mono text-sm font-semibold">{{ $order->order_number }}</span>
                        <x-status-badge :status="$order->status" />
                    </div>
                    <div class="mt-1 text-xs text-ink/60">
                        {{ $order->created_on?->format('d M Y H:i') }} &middot;
                        <span class="capitalize">{{ $order->fulfillment }}</span>
                    </div>
                    <div class="mt-1 text-lg font-bold text-moss">{{ rupiah($order->total) }}</div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('track', ['order' => $order->order_number, 'page' => $orders->currentPage()]) }}"
                        class="btn-primary">View status</a>

                    @if ($canCancel)
                        @if ($cancelState === \App\Models\Transaction::CANCEL_PENDING)
                        <span class="text-xs text-ink/60">Cancellation requested</span>
                        @elseif ($cancelState === \App\Models\Transaction::CANCEL_REJECTED)
                        <span class="text-xs text-ink/60">Cancellation request declined. Please contact us via WhatsApp.</span>
                        @else
                        <button type="button" @click="open = ! open"
                            class="rounded-full border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-700 transition hover:bg-red-50">
                            Cancel order
                        </button>
                        @endif
                    @endif
                </div>

                @if ($canCancel && blank($cancelState))
                <form x-show="open" x-cloak method="POST" action="{{ route('track.cancel', $order) }}"
                    onsubmit="return confirm('Cancel this order?')"
                    class="w-full space-y-2 border-t border-blush/80 pt-4">
                    @csrf
                    <input type="hidden" name="cancel_order_id" value="{{ $order->id }}">

                    <label class="block text-sm font-medium">Reason for cancelling</label>
                    <textarea name="reason" rows="2" required minlength="5" maxlength="255" class="input"
                        placeholder="Tell us why you want to cancel (at least 5 characters)">{{ $reasonFailed ? old('reason') : '' }}</textarea>

                    @if ($reasonFailed)
                    <p class="text-sm text-red-600">{{ $errors->first('reason') }}</p>
                    @endif

                    <p class="text-xs text-ink/60">
                        {{ $order->status === \App\Models\Transaction::PROCESSING
                            ? 'This order is already paid. Our team will review your request before it is cancelled.'
                            : 'Your unpaid order will be cancelled right away.' }}
                    </p>

                    <button class="rounded-full border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-700 transition hover:bg-red-50">
                        Confirm cancellation
                    </button>
                </form>
                @endif
            </div>
            @empty
            <x-ui.empty-state icon="flower-2" title="No orders yet"
                description="Your orders will show up here after you check out.">
                <a href="{{ route('catalog') }}" class="btn-primary">Browse flowers</a>
            </x-ui.empty-state>
            @endforelse
        </div>

        <div class="mt-8">{{ $orders->links() }}</div>
    </div>
</x-public-layout>
