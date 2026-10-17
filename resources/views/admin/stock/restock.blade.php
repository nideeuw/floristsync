<x-staff-layout title="Restock">
    <x-slot:actions>
        <a href="{{ route('admin.stock.index') }}" class="btn-outline">&larr; Stock</a>
    </x-slot:actions>

    <form method="POST" action="{{ route('admin.stock.restock.store') }}" class="card max-w-3xl p-6">
        @csrf

        <div class="grid gap-4">
            <div>
                <label class="mb-1 block text-sm font-medium">Product</label>
                <select name="product_id" required class="input">
                    <option value="">Select product</option>
                    @foreach ($products as $product)
                    <option value="{{ $product->id }}" @selected(old('product_id', request('product')) == $product->id)>
                        {{ $product->product_name }} (stock: {{ $product->stock }})
                    </option>
                    @endforeach
                </select>
                @error('product_id')
                <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <x-ui.field name="quantity" label="Quantity" type="number" :value="1" min="1" />

            <x-ui.textarea name="notes" label="Note" maxlength="200"
                hint="Optional. Defaults to Restock." />
        </div>

        <div class="mt-6 flex gap-2">
            <button class="btn-primary">Save</button>
            <a href="{{ route('admin.stock.index') }}" class="btn-outline">Cancel</a>
        </div>
    </form>
</x-staff-layout>
