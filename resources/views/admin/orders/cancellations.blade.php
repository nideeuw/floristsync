<x-staff-layout title="Order Cancellations">

    <x-ui.table class="[&_table]:min-w-[64rem]">
        <x-slot:head>
            <th>Order</th>
            <th>Requested</th>
            <th>Customer</th>
            <th>Items</th>
            <th>Total</th>
            <th>Reason</th>
            <th class="text-right">Action</th>
        </x-slot:head>

        <x-slot:body>
            @forelse ($requests as $order)
            <tr>
                <td>
                    <div class="font-mono text-xs">{{ $order->order_number }}</div>
                    <a href="{{ route('admin.orders.show', $order) }}" class="text-xs font-semibold text-moss hover:underline">View order</a>
                </td>
                <td class="whitespace-nowrap">{{ $order->cancel_requested_at?->format('d M Y, H:i') ?? '-' }}</td>
                <td>{{ $order->user?->name ?? '-' }}</td>
                <td class="text-xs text-gray-700">
                    @foreach ($order->details as $detail)
                    <div>{{ $detail->qty }} &times; {{ $detail->product?->product_name ?? 'Unknown product' }}</div>
                    @endforeach
                </td>
                <td class="whitespace-nowrap">{{ rupiah($order->total) }}</td>
                <td class="max-w-xs whitespace-pre-line text-sm text-gray-700">{{ $order->cancel_reason ?: '-' }}</td>
                <td class="text-right">
                    <div class="flex justify-end gap-2">
                        <form method="POST" action="{{ route('admin.orders.cancellations.approve', $order) }}"
                            data-confirm="The payment will be refunded and the stock returned."
                            data-confirm-title="Approve this cancellation?"
                            data-confirm-label="Approve"
                            data-confirm-tone="primary">
                            @csrf
                            @method('PATCH')
                            <button class="btn-primary whitespace-nowrap">Approve</button>
                        </form>

                        <form method="POST" action="{{ route('admin.orders.cancellations.reject', $order) }}"
                            data-confirm="The order will keep being processed."
                            data-confirm-title="Reject this cancellation?"
                            data-confirm-label="Reject"
                            data-confirm-tone="danger">
                            @csrf
                            @method('PATCH')
                            <button class="btn-outline whitespace-nowrap">Reject</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="!py-8 text-center text-gray-500">No cancellation requests waiting for review.</td>
            </tr>
            @endforelse
        </x-slot:body>

        <x-slot:footer>{{ $requests->links() }}</x-slot:footer>
    </x-ui.table>
</x-staff-layout>