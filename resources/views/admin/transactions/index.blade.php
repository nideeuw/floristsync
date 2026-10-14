<x-staff-layout title="Transaction History">

    @if ($errors->any())
    <div class="mb-4 rounded-xl bg-red-100 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.transactions') }}" class="card mb-4 grid gap-3 p-4 md:grid-cols-4">
        <div class="md:col-span-2">
            <label class="mb-1 block text-xs text-gray-500">Order number</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="e.g. POS-000123 or online order ID" class="input">
        </div>

        <div>
            <label class="mb-1 block text-xs text-gray-500">Type</label>
            <select name="type" class="input">
                <option value="">POS and online</option>
                <option value="pos" @selected(request('type')==='pos' )>POS (in store)</option>
                <option value="online" @selected(request('type')==='online' )>Online</option>
            </select>
        </div>

        <div>
            <label class="mb-1 block text-xs text-gray-500">Status</label>
            <select name="status" class="input">
                <option value="">All statuses</option>
                @foreach ($statuses as $value => $label)
                <option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="mb-1 block text-xs text-gray-500">Payment method</label>
            <select name="method" class="input">
                <option value="">All methods</option>
                @foreach ($methods as $method)
                <option value="{{ $method }}" @selected(request('method')===$method)>{{ strtoupper($method) }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="mb-1 block text-xs text-gray-500">From</label>
            <input type="date" name="from" value="{{ request('from') }}" class="input">
        </div>

        <div>
            <label class="mb-1 block text-xs text-gray-500">To</label>
            <input type="date" name="to" value="{{ request('to') }}" class="input">
        </div>

        <div class="flex items-end gap-2">
            <button class="btn-primary">Filter</button>
            <a href="{{ route('admin.transactions') }}" class="btn-outline">Reset</a>
        </div>
    </form>

    <x-ui.table class="[&_table]:min-w-[64rem]">
        <x-slot:head>
            <th>Order</th>
            <th>Date</th>
            <th>Type</th>
            <th>Customer / Cashier</th>
            <th>Payment</th>
            <th>Total</th>
            <th>Status</th>
            <th class="text-right">Action</th>
        </x-slot:head>

        <x-slot:body>
            @forelse ($transactions as $transaction)
            <tr>
                <td class="font-mono text-xs">{{ $transaction->order_number }}</td>
                <td class="whitespace-nowrap">{{ \Carbon\Carbon::parse($transaction->created_on)->format('d M Y, H:i') }}</td>
                <td>
                    @if ($transaction->transaction_type === 'pos')
                    <span class="rounded-full bg-blush px-2.5 py-0.5 text-xs font-semibold">POS</span>
                    @else
                    <span class="rounded-full bg-sage/30 px-2.5 py-0.5 text-xs font-semibold">Online</span>
                    @endif
                </td>
                <td>
                    <div>{{ $transaction->user?->name ?? 'No member' }}</div>
                    @if ($transaction->cashier)
                    <div class="text-xs text-gray-500">Cashier: {{ $transaction->cashier->name }}</div>
                    @endif
                </td>
                <td>
                    <div>{{ strtoupper($transaction->payment?->payment_method ?? '-') }}</div>
                    <div class="text-xs text-gray-500">{{ $transaction->payment?->payment_status ?? '-' }}</div>
                </td>
                <td class="whitespace-nowrap">{{ rupiah($transaction->total) }}</td>
                <td>
                    <x-status-badge :status="$transaction->status" />
                    @if ($transaction->status === 'cancelled')
                    <div class="mt-1 max-w-[14rem] text-xs text-gray-600">
                        <div>{{ \Illuminate\Support\Str::limit($transaction->cancel_reason ?? 'No reason given', 60) }}</div>
                        <div class="text-gray-500">
                            By {{ $transaction->cancelledBy?->name ?? '-' }}
                            @if ($transaction->cancelled_at)
                            &middot; {{ $transaction->cancelled_at->format('d M Y, H:i') }}
                            @endif
                        </div>
                    </div>
                    @endif
                </td>
                <td class="text-right">
                    <a href="{{ route('admin.transactions.show', $transaction) }}" class="font-semibold text-moss hover:underline">View</a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="!py-8 text-center text-gray-500">No transactions found.</td>
            </tr>
            @endforelse
        </x-slot:body>

        <x-slot:footer>{{ $transactions->links() }}</x-slot:footer>
    </x-ui.table>
</x-staff-layout>
