<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrackController extends Controller
{
    public function index(Request $request): View
    {
        $orderNumber = trim((string) $request->query('order', ''));
        $order = null;

        if ($orderNumber !== '') {
            $order = strtoupper($orderNumber) === 'DEMO'
                ? $this->demoOrder()
                : $this->findOrder($orderNumber);
        }

        return view('customer.track', [
            'orderNumber' => $orderNumber,
            'order' => $order,
        ]);
    }

    private function findOrder(string $orderNumber): ?array
    {
        $transaction = Transaction::with('details.product', 'payment')
            ->whereHas('payments', fn ($q) => $q->where('midtrans_order_id', $orderNumber))
            ->first();

        if (! $transaction) {
            return null;
        }

        return [
            'number' => $orderNumber,
            'status' => $transaction->status,
            'delivery' => $transaction->isDelivery(),
            'courier' => $transaction->courier,
            'tracking_number' => $transaction->tracking_number,
            'total' => $transaction->total,
            'items' => $transaction->details->map(fn ($d) => [
                'name' => $d->product->product_name,
                'qty' => $d->qty,
                'subtotal' => $d->subtotal,
            ])->all(),
        ];
    }

    /** Fake order so the page can be previewed: /track?order=DEMO */
    private function demoOrder(): array
    {
        return [
            'number' => 'DEMO',
            'status' => 'shipped',
            'delivery' => true,
            'courier' => 'jne',
            'tracking_number' => 'JNE1234567890',
            'total' => 535000,
            'items' => [
                ['name' => 'Blush Rose Bouquet', 'qty' => 1, 'subtotal' => 450000],
                ['name' => 'Sage Tulip Stem', 'qty' => 2, 'subtotal' => 60000],
            ],
        ];
    }
}
