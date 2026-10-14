<?php

namespace App\Services;

use App\Models\Transaction;
use DomainException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Midtrans\Config;
use Midtrans\Notification;
use Midtrans\Snap;
use Midtrans\Transaction as MidtransApi;
use Throwable;

class MidtransService
{
    private const ENABLED_PAYMENTS = ['credit_card', 'gopay', 'shopeepay', 'other_qris'];

    private const PAYMENT_WINDOW_MINUTES = 60;

    public function __construct()
    {
        Config::$serverKey = config('services.midtrans.server_key');
        Config::$isProduction = (bool) config('services.midtrans.is_production');
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    public function createSnapToken(Transaction $transaction, array $customer = []): string
    {
        $items = $transaction->details->map(fn($d) => [
            'id' => (string) $d->product_id,
            'price' => (int) $d->unit_price,
            'quantity' => (int) $d->qty,
            'name' => Str::limit($d->product->product_name, 50, ''),
        ])->values()->all();

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
            'enabled_payments' => self::ENABLED_PAYMENTS,
            'expiry' => [
                'unit' => 'minutes',
                'duration' => self::PAYMENT_WINDOW_MINUTES,
            ],
        ]);
    }

    public function readNotification(): array
    {
        $payload = json_decode(request()->getContent(), true);

        if (! is_array($payload) || ! $this->hasValidSignature($payload)) {
            throw new DomainException('Invalid Midtrans notification signature.');
        }

        $raw = (array) (new Notification())->getResponse();

        return [
            'order_id' => $raw['order_id'],
            'transaction_status' => $raw['transaction_status'],
            'fraud_status' => $raw['fraud_status'] ?? null,
            'payment_type' => $raw['payment_type'] ?? null,
            'gross_amount' => $raw['gross_amount'] ?? null,
        ];
    }

    public function cancelInvoice(string $orderId): void
    {
        try {
            MidtransApi::cancel($orderId);
        } catch (Throwable $e) {
            Log::warning("Midtrans cancel skipped for {$orderId}: {$e->getMessage()}");
        }
    }

    private function hasValidSignature(array $payload): bool
    {
        $expected = hash('sha512',
            ($payload['order_id'] ?? '')
            .($payload['status_code'] ?? '')
            .($payload['gross_amount'] ?? '')
            .config('services.midtrans.server_key')
        );

        return hash_equals($expected, (string) ($payload['signature_key'] ?? ''));
    }
}
