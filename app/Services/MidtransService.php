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

    public function getStatus(string $orderId): ?array
    {
        try {
            return (array) MidtransApi::status($orderId);
        } catch (Throwable $e) {
            if ($this->errorCode($e) === 404) {
                return null;
            }

            throw $this->toDomainException($e);
        }
    }

    public function cancelTransaction(string $orderId): void
    {
        try {
            MidtransApi::cancel($orderId);
        } catch (Throwable $e) {
            throw $this->toDomainException($e);
        }
    }

    public function refundTransaction(string $orderId, array $params): array
    {
        try {
            $response = (array) MidtransApi::refund($orderId, $params);
        } catch (Throwable $e) {
            throw $this->toDomainException($e);
        }

        if (! in_array((string) ($response['status_code'] ?? ''), ['200', '201'], true)) {
            throw new DomainException($response['status_message'] ?? 'Midtrans rejected the refund.');
        }

        return $response;
    }

    private function errorCode(Throwable $e): int
    {
        if (preg_match('/HTTP status code: (\d+)/', $e->getMessage(), $match)) {
            return (int) $match[1];
        }

        return (int) $e->getCode();
    }

    private function toDomainException(Throwable $e): DomainException
    {
        Log::warning('Midtrans API error: '.$e->getMessage());

        if (preg_match('/API response: (\{.*\})/s', $e->getMessage(), $match)) {
            $body = json_decode($match[1], true);

            if (is_array($body) && ! empty($body['status_message'])) {
                return new DomainException($body['status_message'], 0, $e);
            }
        }

        return new DomainException('Midtrans is not reachable right now. Please try again.', 0, $e);
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
