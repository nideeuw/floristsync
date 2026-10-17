<x-public-layout title="Order History">
    @php
        $cancellable = [\App\Models\Transaction::PENDING_PAYMENT, \App\Models\Transaction::PROCESSING];
    @endphp

    <div class="mx-auto max-w-3xl px-4 py-8 md:py-12">
        <x-page-header eyebrow="My orders" title="Order history"
            description="Your orders, newest first. Pick an order to see its status." />

        @if ($orderNumber !== '' && ! $selected)
        <x-ui.empty-state icon="search-x" title="Order not found"
            description="We could not find that order in your account." class="mt-8" />
        @endif

        {{-- Fallback: the chosen order is not on the page being shown --}}
        @if ($selected && ! $orders->contains('id', $selected->id))
        <div class="mt-8">
            @include('customer.partials.order-detail', ['selected' => $selected, 'tracking' => $tracking])
        </div>
        @endif

        {{-- Own orders --}}
        <div class="mt-8 space-y-4">
            @forelse ($orders as $order)
            @php
                $isSelected = $selected && $selected->id === $order->id;
                $canCancel = in_array($order->status, $cancellable, true);
                $canPay = app(\App\Services\PaymentResumeService::class)->canResume($order);
                $payDeadline = $canPay ? app(\App\Services\PaymentResumeService::class)->deadline($order) : null;
                $cancelState = $order->cancel_request_status;
                $reasonFailed = $errors->has('reason') && (int) old('cancel_order_id') === (int) $order->id;
            @endphp
            <div id="{{ $isSelected ? 'selected-order' : 'order-'.$order->id }}" class="scroll-mt-24 space-y-3">
                <div x-data="{ open: {{ $reasonFailed ? 'true' : 'false' }} }"
                    class="flex flex-wrap items-center justify-between gap-4 rounded-[2rem] bg-white p-5 shadow-sm ring-1 transition hover:shadow-md
                            {{ $isSelected ? 'ring-2 ring-sage' : 'ring-blush/60' }}">
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
                        <a href="{{ $isSelected
                                ? route('track', ['page' => $orders->currentPage()])
                                : route('track', ['order' => $order->order_number, 'page' => $orders->currentPage()]).'#selected-order' }}"
                            class="btn-primary">{{ $isSelected ? 'Hide status' : 'View status' }}</a>

                        @if ($order->canResumePayment())
                        <a href="{{ route('track.pay', $order) }}" class="btn-primary">Pay now</a>
                        <span class="text-xs text-ink/60">Pay before {{ $order->paymentDeadline()->format('H:i') }}</span>
                        @endif

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
                        data-confirm="You can not undo this. Do you want to continue?"
                        data-confirm-title="Cancel this order?"
                        data-confirm-label="Yes, cancel order"
                        data-confirm-cancel-label="Keep order"
                        data-confirm-tone="danger"
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

                @if ($isSelected)
                @include('customer.partials.order-detail', ['selected' => $selected, 'tracking' => $tracking])
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
