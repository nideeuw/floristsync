<?php

namespace App\Services;

use App\Models\Transaction;
use DomainException;
use Illuminate\Support\Str;

class PaymentReversalService
{
    private const REFUNDABLE_METHODS = ['credit_card', 'gopay', 'shopeepay', 'qris'];

    private const CLOSED_STATUSES = ['cancel', 'expire', 'deny', 'failure'];

    public function __construct(private MidtransService $midtrans) {}

    public function cancelInvoice(Transaction $transaction): void
    {
        $payment = $transaction->payment;

        if (! $payment || ! $payment->midtrans_order_id || in_array($payment->payment_status, self::CLOSED_STATUSES, true)) {
            return;
        }

        if ($payment->payment_date !== null) {
            throw new DomainException('This order is already paid. Refund it instead.');
        }

        $remote = $this->midtrans->getStatus($payment->midtrans_order_id);
        $status = 'cancel';

        if ($remote !== null) {
            $remoteStatus = $remote['transaction_status'];

            if ($this->isPaid($remote)) {
                throw new DomainException('This order is already paid. Refund it instead.');
            }

            if (in_array($remoteStatus, self::CLOSED_STATUSES, true)) {
                $status = $remoteStatus;
            } elseif (in_array($remoteStatus, ['pending', 'authorize', 'capture'], true)) {
                $this->midtrans->cancelTransaction($payment->midtrans_order_id);
            } else {
                throw new DomainException("This invoice cannot be cancelled (Midtrans status: {$remoteStatus}).");
            }
        }

        $payment->update(['payment_status' => $status]);
    }

    public function refund(Transaction $transaction): void
    {
        $payment = $transaction->payment;

        if (! $payment || ! $payment->midtrans_order_id) {
            throw new DomainException('This order was not paid through Midtrans, so it cannot be refunded here.');
        }

        if ($payment->payment_status === 'refund') {
            return;
        }

        $orderId = $payment->midtrans_order_id;
        $remote = $this->midtrans->getStatus($orderId);

        if ($remote === null) {
            throw new DomainException('Midtrans has no payment recorded for this order.');
        }

        $remoteStatus = $remote['transaction_status'];

        if ($remoteStatus === 'refund') {
            $payment->update(['payment_status' => 'refund']);

            return;
        }

        $method = $remote['payment_type'] ?? $payment->payment_method;

        if (! in_array($method, self::REFUNDABLE_METHODS, true)) {
            throw new DomainException('Refund is only available for card, GoPay, ShopeePay and QRIS payments.');
        }

        if ($remoteStatus === 'capture' && ($remote['fraud_status'] ?? null) === 'accept') {
            $this->midtrans->cancelTransaction($orderId);
            $payment->update(['payment_status' => 'cancel']);

            return;
        }

        if ($remoteStatus !== 'settlement') {
            throw new DomainException("Only paid orders can be refunded (Midtrans status: {$remoteStatus}).");
        }

        try {
            $response = $this->midtrans->refundTransaction($orderId, [
                'refund_key' => 'RF-' . $orderId,
                'amount' => (int) round((float) $remote['gross_amount']),
                'reason' => Str::limit("Refund for order #{$transaction->id}", 255, ''),
            ]);
        } catch (DomainException $e) {
            if (! str_contains($e->getMessage(), 'Duplicate refund ID')) {
                throw $e;
            }

            $response = $this->midtrans->getStatus($orderId) ?? [];
        }

        $payment->update(['payment_status' => $response['transaction_status'] ?? 'refund']);
    }

    private function isPaid(array $remote): bool
    {
        return $remote['transaction_status'] === 'settlement'
            || ($remote['transaction_status'] === 'capture' && ($remote['fraud_status'] ?? null) === 'accept');
    }
}
