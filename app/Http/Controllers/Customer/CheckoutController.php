<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\CartService;
use App\Services\TransactionService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class CheckoutController extends Controller
{
    public function __construct(
        private CartService $cart,
        private TransactionService $transactions,
    ) {}

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

    public function store(Request $request): View|RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150'],
            'fulfillment' => ['required', 'in:pickup,delivery'],
            'address' => ['required_if:fulfillment,delivery', 'nullable', 'string', 'max:500'],
            'destination_id' => ['required_if:fulfillment,delivery', 'nullable', 'integer'],
            'courier' => ['required_if:fulfillment,delivery', 'nullable', 'in:jne,tiki,pos,sicepat,jnt'],
            'service' => ['required_if:fulfillment,delivery', 'nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $items = $this->cart->items();

        if ($items->isEmpty()) {
            return redirect()->route('cart')->with('error', 'Your cart is empty.');
        }

        $lines = $items
            ->map(fn ($line) => ['product_id' => $line->product->id, 'qty' => $line->qty])
            ->all();

        $isDelivery = $data['fulfillment'] === 'delivery';

        try {
            $transaction = $this->transactions->createOnlineTransaction(
                $lines,
                [
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'email' => $data['email'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ],
                [
                    'method' => $data['fulfillment'],
                    'address' => $isDelivery ? $data['address'] : null,
                    'destination_id' => $isDelivery ? $data['destination_id'] : null,
                    'courier' => $isDelivery ? $data['courier'] : null,
                    'service' => $isDelivery ? $data['service'] : null,
                ],
                $request->user()->id,
            );
        } catch (DomainException|RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $this->cart->clear();

        return $this->paymentView($transaction, $transaction->payment->snap_token);
    }

    private function paymentView(Transaction $transaction, string $snapToken): View
    {
        return view('customer.payment', [
            'transaction' => $transaction,
            'snapToken' => $snapToken,
            'clientKey' => config('services.midtrans.client_key'),
            'snapUrl' => config('services.midtrans.is_production')
                ? 'https://app.midtrans.com/snap/snap.js'
                : 'https://app.sandbox.midtrans.com/snap/snap.js',
        ]);
    }
}