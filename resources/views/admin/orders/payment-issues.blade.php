@php
$titles = [
    'pending' => ['Unpaid for more than 60 minutes', 'Online orders that are still waiting for payment after the payment window. The webhook or the expire job may have been missed.'],
    'late_paid' => ['Paid after cancellation', 'The customer paid after the order was cancelled. The payment must be refunded.'],
    'failed_refunds' => ['Failed refunds', 'Midtrans rejected the refund. Retry it from the Order Cancellations page.'],
    'unconfirmed' => ['Payment not settled yet', 'Authorized or captured more than 24 hours ago and still not settled. Midtrans may already have settled it.'],
];
@endphp

<x-staff-layout title="Payment Issues">
    @foreach ($titles as $key => [$title, $hint])
    <div class="mb-8">
        <div class="mb-1 font-semibold">
            {{ $title }}
            <span class="ml-1 rounded-full bg-blush px-2 py-0.5 text-xs">{{ $sections[$key]->count() }}</span>
        </div>
        <p class="mb-3 text-sm text-gray-500">{{ $hint }}</p>

        <x-ui.table class="[&_table]:min-w-[52rem]">
            <x-slot:head>
                <th>Order</th>
                <th>Customer</th>
                <th>Total</th>
                <th>Order status</th>
                <th>Payment status</th>
                <th>Date</th>
                <th class="text-right">Action</th>
            </x-slot:head>

            <x-slot:body>
                @forelse ($sections[$key] as $order)
                <tr>
                    <td>
                        <div class="font-mono text-xs">{{ $order->order_number }}</div>
                        <a href="{{ route('admin.orders.show', $order) }}" class="text-xs font-semibold text-moss hover:underline">View order</a>
                    </td>
                    <td>{{ $order->user?->name ?? '-' }}</td>
                    <td class="whitespace-nowrap">{{ rupiah($order->total) }}</td>
                    <td><x-status-badge :status="$order->status" /></td>
                    <td>
                        {{ $order->payment?->payment_status ?? '-' }}
                        @if ($order->payment?->refund_status)
                        <div class="text-xs text-gray-500">Refund: {{ $order->payment->refund_status }}</div>
                        @endif
                    </td>
                    <td class="whitespace-nowrap">{{ \Carbon\Carbon::parse($order->created_on)->format('d M Y, H:i') }}</td>
                    <td class="text-right">
                        @if ($canAct)
                        <form method="POST" action="{{ route('admin.orders.payment-issues.recheck', $order) }}">
                            @csrf
                            <button class="btn-outline whitespace-nowrap">Re-check with Midtrans</button>
                        </form>
                        @else
                        <span class="text-xs text-gray-400">Read only</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="!py-6 text-center text-gray-500">Nothing here.</td>
                </tr>
                @endforelse
            </x-slot:body>

            <x-slot:footer></x-slot:footer>
        </x-ui.table>
    </div>
    @endforeach
</x-staff-layout>
