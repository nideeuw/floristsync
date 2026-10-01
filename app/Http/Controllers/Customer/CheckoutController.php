<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(private CartService $cart) {}

    public function index(): View|RedirectResponse
    {
        $items = $this->cart->items();

        if ($items->isEmpty()) {
            return redirect()->route('cart')->with('error', 'Your cart is empty.');
        }

        return view('customer.checkout', [
            'items' => $items,
            'subtotal' => $items->sum('subtotal'),
        ]);
    }

    /** Skeleton only: validates the form. Midtrans payment is connected in a later step. */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150'],
            'fulfillment' => ['required', 'in:pickup,delivery'],
            'address' => ['required_if:fulfillment,delivery', 'nullable', 'string', 'max:500'],
            'courier' => ['required_if:fulfillment,delivery', 'nullable', 'in:jne,tiki,pos,sicepat,jnt'],
        ]);

        return back()
            ->withInput()
            ->with('success', 'Form is valid. Payment (Midtrans) will be connected in the next step.');
    }
}
