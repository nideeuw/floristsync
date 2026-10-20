@php
$tabs = [
'' => 'All',
'processing' => 'To ship',
'shipped' => 'Shipped',
];
@endphp

<x-staff-layout title="Shipping & Tracking">

    @if ($errors->has('tracking_number'))
    <div class="mb-4 rounded-xl bg-red-100 px-4 py-3 text-sm text-red-800">{{ $errors->first('tracking_number') }}</div>
    @endif

    {{-- Status tabs --}}
    <div class="mb-4 flex flex-wrap gap-2">
        @foreach ($tabs as $key => $label)
        @php($count = $key === '' ? $counts->sum() : $counts->get($key, 0))

        <a href="{{ route('admin.orders.shipping', array_filter(['status' => $key])) }}"
            class="rounded-full px-4 py-1.5 text-sm font-semibold {{ $current === (string) $key ? 'bg-moss text-white' : 'bg-white hover:bg-blush' }}">
            {{ $label }}
            <span class="ml-1 text-xs opacity-80">{{ $count }}</span>
        </a>
        @endforeach
    </div>

    <x-ui.table class="[&_table]:min-w-[64rem]">
        <x-slot:head>
            <th>Order</th>
            <th>Date</th>
            <th>Recipient &amp; destination</th>
            <th>Courier</th>
            <th>Tracking number (resi)</th>
            <th>Status</th>
            <th class="text-right">Action</th>
        </x-slot:head>

        <x-slot:body>
            @forelse ($orders as $order)
            <tr>
                <td class="font-mono text-xs">{{ $order->order_number }}</td>
                <td class="whitespace-nowrap">{{ \Carbon\Carbon::parse($order->created_on)->format('d M Y, H:i') }}</td>
                <td>
                    <div class="font-semibold">{{ \Illuminate\Support\Str::before($order->shipping_address, "\n") }}</div>
                    <div class="max-w-xs text-xs text-gray-500">{{ \Illuminate\Support\Str::limit(\Illuminate\Support\Str::after($order->shipping_address, "\n"), 90) }}</div>
                </td>
                <td class="font-semibold">{{ $order->shipping_service_label ?? strtoupper($order->courier) }}</td>
                <td>
                    @if ($order->status === 'processing')
                    <form method="POST" action="{{ route('admin.orders.tracking', $order) }}" class="flex gap-2">
                        @csrf
                        @method('PATCH')
                        <input name="tracking_number" required maxlength="50" class="input" placeholder="e.g. JNE1234567890">
                        <button class="btn-primary whitespace-nowrap">Ship</button>
                    </form>
                    @else
                    <span class="font-mono text-xs">{{ $order->tracking_number ?? '-' }}</span>
                    @endif
                </td>
                <td><x-status-badge :status="$order->status" /></td>
                <td class="text-right">
                    <a href="{{ route('admin.orders.show', $order) }}" class="font-semibold text-moss hover:underline">View</a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="!py-8 text-center text-gray-500">No delivery orders to show.</td>
            </tr>
            @endforelse
        </x-slot:body>

        <x-slot:footer>{{ $orders->links() }}</x-slot:footer>
    </x-ui.table>
</x-staff-layout>