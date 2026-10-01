<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\CartService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(private CartService $cart) {}

    public function index(): View
    {
        $items = $this->cart->items();

        return view('customer.cart', [
            'items' => $items,
            'subtotal' => $items->sum('subtotal'),
        ]);
    }

    public function add(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->is_active && $product->status === Product::STATUS_AVAILABLE, 404);

        $data = $request->validate(['qty' => ['nullable', 'integer', 'min:1', 'max:99']]);

        try {
            $this->cart->add($product, $data['qty'] ?? 1);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "{$product->product_name} added to cart.");
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate(['qty' => ['required', 'integer', 'min:0', 'max:99']]);

        $this->cart->update($product, $data['qty']);

        return back()->with('success', 'Cart updated.');
    }

    public function remove(Product $product): RedirectResponse
    {
        $this->cart->remove($product);

        return back()->with('success', 'Item removed.');
    }
}
