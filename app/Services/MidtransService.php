<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Str;
use Midtrans\Config;
use Midtrans\Notification;
use Midtrans\Snap;

class MidtransService
{
    public function __construct()
    {
        Config::$serverKey = config('services.midtrans.server_key');
        Config::$isProduction = (bool) config('services.midtrans.is_production');
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    /**
     * Transaction must have `details.product` and `payment` loaded.
     * $customer = ['name', 'email', 'phone'] (sent to Midtrans only, not stored).
     */
    public function createSnapToken(Transaction $transaction, array $customer = []): string
    {
        $items = $transaction->details->map(fn($d) => [
            'id' => (string) $d->product_id,
            'price' => (int) $d->unit_price,
            'quantity' => (int) $d->qty,
            'name' => Str::limit($d->product->product_name, 50, ''),
        ])->values()->all();

        // Shipping cost = total - sum of item subtotals.
        $shippingCost = (int) $transaction->total - (int) $transaction->details->sum('subtotal');

        if ($shippingCost > 0) {
            $items[] = ['id' => 'SHIPPING', 'price' => $shippingCost, 'quantity' => 1, 'name' => 'Shipping Cost'];
        }

        return Snap::getSnapToken([
            'transaction_details' => [
                'order_id' => $transaction->payment->midtrans_order_id,
                'gross_amount' => (int) $transaction->total,
            ],
            'customer_details' => array_filter([
                'first_name' => $customer['name'] ?? 'Customer',
                'email' => $customer['email'] ?? null,
                'phone' => $customer['phone'] ?? null,
            ]),
            'item_details' => $items,
        ]);
    }

    /** Read the Midtrans webhook. The library verifies the status directly with Midtrans. */
    public function readNotification(): array
    {
        $raw = (array) (new Notification())->getResponse();

        return [
            'order_id' => $raw['order_id'],
            'transaction_status' => $raw['transaction_status'],
            'fraud_status' => $raw['fraud_status'] ?? null,
            'payment_type' => $raw['payment_type'] ?? null,
        ];
    }
}
