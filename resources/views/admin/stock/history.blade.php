<x-staff-layout title="Stock History">
    <x-slot:actions>
        <a href="{{ route('admin.stock.index') }}" class="btn-outline">&larr; Stock</a>
    </x-slot:actions>

    <form method="GET" class="card mb-4 grid gap-3 p-4 md:grid-cols-5">
        <select name="product" class="input">
            <option value="">All products</option>
            @foreach ($products as $product)
            <option value="{{ $product->id }}" @selected(request('product')==$product->id)>{{ $product->product_name }}</option>
            @endforeach
        </select>

        <select name="type" class="input">
            <option value="">All types</option>
            @foreach ($types as $type)
            <option value="{{ $type }}" @selected(request('type')==$type)>{{ ucfirst($type) }}</option>
            @endforeach
        </select>

        <input type="date" name="date_from" value="{{ request('date_from') }}" class="input" title="From date">

        <input type="date" name="date_to" value="{{ request('date_to') }}" class="input" title="To date">

        <div class="flex gap-2">
            <button class="btn-primary">Filter</button>
            <a href="{{ route('admin.stock.history') }}" class="btn-outline">Reset</a>
        </div>
    </form>

    <x-ui.table>
        <x-slot:head>
            <th>Date</th>
            <th>Product</th>
            <th>Change</th>
            <th>Type</th>
            <th>Notes</th>
            <th>By</th>
        </x-slot:head>

        <x-slot:body>
            @forelse ($stocks as $stock)
            <tr>
                <td class="whitespace-nowrap">{{ $stock->date?->format('d M Y H:i') }}</td>
                <td class="font-medium">{{ $stock->product?->product_name }}</td>
                <td>
                    <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $stock->quantity_change < 0 ? 'bg-red-100 text-red-700' : 'bg-sage/30' }}">
                        {{ $stock->quantity_change > 0 ? '+' : '' }}{{ $stock->quantity_change }}
                    </span>
                </td>
                <td>
                    <span class="rounded-full px-2 py-1 text-xs {{ match ($stock->change_type) {
                        'in' => 'bg-sage/30',
                        'out' => 'bg-red-100 text-red-700',
                        'cancel' => 'bg-amber-100 text-amber-700',
                        default => 'bg-gray-200 text-gray-600',
                    } }}">{{ ucfirst($stock->change_type) }}</span>
                </td>
                <td class="max-w-xs truncate">{{ $stock->notes }}</td>
                <td class="whitespace-nowrap">{{ $stock->creator?->name ?? '-' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="!py-8 text-center text-gray-500">No stock movements found.</td>
            </tr>
            @endforelse
        </x-slot:body>

        <x-slot:footer>{{ $stocks->links() }}</x-slot:footer>
    </x-ui.table>
</x-staff-layout>
