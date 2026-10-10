<x-public-layout title="Cart">
    <div class="mx-auto max-w-7xl px-4 py-10">
        <h1 class="mb-6 text-3xl font-bold">Your Cart</h1>

        @if ($items->isEmpty())
        <x-ui.empty-state icon="shopping-cart" title="Your cart is empty"
            description="Add some flowers and they will show up here.">
            <a href="{{ route('catalog') }}" class="btn-primary">Browse Flowers</a>
        </x-ui.empty-state>
        @else
        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Items --}}
            <div class="space-y-4 lg:col-span-2">
                @foreach ($items as $item)
                <div class="card flex items-center gap-4 p-4">
                    <x-product-image :product="$item->product" class="h-24 w-24 shrink-0 rounded-xl" />

                    <div class="min-w-0 flex-1">
                        <a href="{{ route('products.show', $item->product) }}"
                            class="font-semibold hover:text-moss">{{ $item->product->product_name }}</a>
                        <div class="text-sm text-gray-600">{{ rupiah($item->product->price) }}</div>

                        <form method="POST" action="{{ route('cart.update', $item->product) }}"
                            class="mt-2 flex items-center gap-2">
                            @csrf
                            @method('PATCH')
                            <input type="number" name="qty" value="{{ $item->qty }}" min="0"
                                max="{{ $item->product->stock }}" class="input w-20">
                            <button class="btn-outline px-3 py-1.5 text-xs">Update</button>
                        </form>
                    </div>

                    <div class="text-right">
                        <div class="font-bold">{{ rupiah($item->subtotal) }}</div>
                        <form method="POST" action="{{ route('cart.remove', $item->product) }}" class="mt-2">
                            @csrf
                            @method('DELETE')
                            <button class="text-xs text-red-600 hover:underline">Remove</button>
                        </form>
                    </div>
                </div>
                @endforeach
            </div>

            {{-- Summary --}}
            <div>
                <div class="card p-6">
                    <h2 class="text-lg font-bold">Order Summary</h2>
                    <div class="mt-4 flex justify-between text-sm">
                        <span>Subtotal</span>
                        <span class="font-semibold">{{ rupiah($subtotal) }}</span>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Shipping is calculated at checkout.</p>

                    @auth
                    <a href="{{ route('checkout') }}" class="btn-primary mt-6 w-full">Proceed to Checkout</a>
                    @else
                    <a href="{{ route('checkout') }}" class="btn-primary mt-6 w-full">Log in to Checkout</a>
                    <p class="mt-2 text-center text-xs text-gray-500">
                        New here?
                        <a href="{{ route('register') }}" class="font-semibold text-moss hover:underline">Create an account</a>
                        to place your order. Your cart will be kept.
                    </p>
                    @endauth

                    <a href="{{ route('catalog') }}" class="btn-outline mt-2 w-full">Continue Shopping</a>
                </div>
            </div>
        </div>
        @endif
    </div>
</x-public-layout>
