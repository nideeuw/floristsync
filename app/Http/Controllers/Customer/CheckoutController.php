<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\CartService;
use App\Services\TransactionService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

class CheckoutController extends Controller
{
    public function __construct(
        private CartService $cart,
        private TransactionService $transactions,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $rawCart = $this->cart->raw();

        if (empty($rawCart)) {
            return redirect()->route('cart')->with('error', 'Your cart is empty.');
        }

        $selectedParam = $request->input('selected', $request->input('items'));

        if ($selectedParam === null) {
            return redirect()->route('cart')->with('error', 'Please select at least one item to check out.');
        }

        $selectedIds = is_array($selectedParam) ? $selectedParam : array_filter(explode(',', (string) $selectedParam));
        $selectedIds = array_values(array_filter(array_map('intval', $selectedIds)));

        if (empty($selectedIds)) {
            return redirect()->route('cart')->with('error', 'Please select at least one item to check out.');
        }

        foreach ($selectedIds as $id) {
            if (! array_key_exists($id, $rawCart)) {
                return redirect()->route('cart')->with('error', 'One or more selected items are not in your cart.');
            }
        }

        $items = $this->cart->items($selectedIds);

        if ($items->isEmpty()) {
            return redirect()->route('cart')->with('error', 'Please select at least one item to check out.');
        }

        return view('customer.checkout', [
            'items' => $items,
            'subtotal' => $items->sum('subtotal'),
        ]);
    }

    public function store(Request $request): RedirectResponse
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
            'selected' => ['nullable'],
        ]);

        $userId = $request->user()->id;

        // One checkout at a time per member: a double click or a second tab must not create two orders.
        $lock = Cache::lock("checkout:{$userId}", 10);

        if (! $lock->get()) {
            return back()->withInput()->with('error', 'Your order is already being placed. Please wait a moment.');
        }

        try {
            return $this->placeOrder($request, $data, $userId);
        } finally {
            $lock->release();
        }
    }

    private function placeOrder(Request $request, array $data, int $userId): RedirectResponse
    {
        $rawCart = $this->cart->raw();

        if (empty($rawCart)) {
            return redirect()->route('cart')->with('error', 'Your cart is empty.');
        }

        if ($request->has('selected') || $request->has('items')) {
            $selectedParam = $request->input('selected', $request->input('items'));
            $selectedIds = is_array($selectedParam) ? $selectedParam : array_filter(explode(',', (string) $selectedParam));
            $selectedIds = array_values(array_filter(array_map('intval', $selectedIds)));

            if (empty($selectedIds)) {
                return redirect()->route('cart')->with('error', 'Please select at least one item to check out.');
            }

            foreach ($selectedIds as $id) {
                if (! array_key_exists($id, $rawCart)) {
                    return redirect()->route('cart')->with('error', 'One or more selected items are not in your cart.');
                }
            }

            $items = $this->cart->items($selectedIds);
        } else {
            $items = $this->cart->items();
        }

        if ($items->isEmpty()) {
            return redirect()->route('cart')->with('error', 'Please select at least one item to check out.');
        }

        // Stock is held while an order waits for payment, so one member may only have a few unpaid orders.
        $max = (int) config('floristsync.max_pending_orders');

        if ($max > 0) {
            $pending = Transaction::where('user_id', $userId)
                ->where('transaction_type', Transaction::TYPE_ONLINE)
                ->where('status', Transaction::PENDING_PAYMENT)
                ->count();

            if ($pending >= $max) {
                return redirect()->route('track')->with(
                    'error',
                    "You have {$pending} unpaid ".Str::plural('order', $pending).'. Please pay or cancel one first.'
                );
            }
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
                $userId,
            );
        } catch (DomainException|RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        // Remove only the ordered items from the cart; unselected items stay
        $orderedIds = collect($lines)->pluck('product_id')->all();
        $this->cart->removeMany($orderedIds);

        // Redirect, so a browser refresh does not post the form again.
        return redirect()->route('track.pay', $transaction->id, 303);
    }
}