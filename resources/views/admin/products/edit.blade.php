<x-staff-layout title="Edit Product">
    <div class="grid gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ route('admin.products.update', $product) }}" enctype="multipart/form-data"
            class="card p-6 lg:col-span-2">
            @csrf
            @method('PUT')
            @include('admin.products._form', ['product' => $product])

            <div class="mt-6 flex gap-2">
                <button class="btn-primary">Update</button>
                <a href="{{ route('admin.products.index') }}" class="btn-outline">Cancel</a>
            </div>
        </form>

        <div class="space-y-6">
            <div class="card p-5">
                <div class="font-semibold">Stock</div>
                <div class="mt-1 text-3xl font-extrabold">{{ $product->stock }}</div>

                <form method="POST" action="{{ route('admin.products.stock', $product) }}" class="mt-4 space-y-2">
                    @csrf
                    @method('PATCH')
                    <label class="block text-sm font-medium">Change (use a minus to remove)</label>
                    <input type="number" name="change" required class="input" placeholder="e.g. 10 or -2">
                    <input type="text" name="notes" class="input" placeholder="Notes (e.g. new delivery)">
                    <button class="btn-secondary w-full">Adjust stock</button>
                </form>
            </div>

            <div class="card overflow-hidden">
                <div class="border-b border-blush px-5 py-3 font-semibold">Stock history</div>
                <ul class="divide-y divide-blush text-sm">
                    @forelse ($history as $row)
                    <li class="flex items-center justify-between px-5 py-2">
                        <div>
                            <div>{{ $row->notes }}</div>
                            <div class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($row->date)->format('d M Y, H:i') }} &middot; {{ $row->change_type }}</div>
                        </div>
                        <span class="font-bold {{ $row->quantity_change < 0 ? 'text-red-600' : 'text-moss' }}">
                            {{ $row->quantity_change > 0 ? '+' : '' }}{{ $row->quantity_change }}
                        </span>
                    </li>
                    @empty
                    <li class="px-5 py-4 text-gray-500">No history yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</x-staff-layout>
