<?php

namespace App\Services;

use App\Exceptions\RefundFailedException;
use App\Models\Payment;
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
            $this->recordRefund($payment, $remote);

            return;
        }

        $method = $remote['payment_type'] ?? $payment->payment_method;

        if (! in_array($method, self::REFUNDABLE_METHODS, true)) {
            throw new DomainException('Refund is only available for card, GoPay, ShopeePay and QRIS payments.');
        }

        if ($remoteStatus === 'capture' && ($remote['fraud_status'] ?? null) === 'accept') {
            // A captured card payment is voided while it is not settled yet.
            try {
                $this->midtrans->cancelTransaction($orderId);
            } catch (DomainException $e) {
                // The void was refused. If the payment settled in the meantime, it needs a refund instead.
                $remote = $this->midtrans->getStatus($orderId);

                if (($remote['transaction_status'] ?? null) !== 'settlement') {
                    $this->markRefundFailed($transaction);

                    throw new RefundFailedException($e->getMessage(), 0, $e);
                }

                $remoteStatus = 'settlement';
            }

            if ($remoteStatus === 'capture') {
                $payment->update(['payment_status' => 'cancel']);

                return;
            }
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
                // Midtrans rejected the refund: it is recorded and the caller keeps the order as it was.
                $this->markRefundFailed($transaction);

                throw new RefundFailedException($e->getMessage(), 0, $e);
            }

            $response = $this->midtrans->getStatus($orderId) ?? [];
        }

        $payment->update(['payment_status' => $response['transaction_status'] ?? 'refund']);
        $this->recordRefund($payment, $response + ['transaction_status' => 'refund']);
    }

    /** Stores what Midtrans says about a refund. Safe to call again for the same refund. */
    public function recordRefund(Payment $payment, array $remote): void
    {
        $status = $remote['transaction_status'] ?? 'refund';
        $done = in_array($status, ['refund', 'partial_refund'], true);
        $amount = $remote['refund_amount'] ?? ($status === 'refund' ? $payment->amount : null);

        $payment->update([
            'refund_status' => $done ? 'success' : 'pending',
            'refund_amount' => $amount === null ? null : (int) round((float) $amount),
            'refunded_at' => $done ? ($payment->refunded_at ?? now()) : null,
            'refund_key' => $remote['refund_key'] ?? 'RF-'.$payment->midtrans_order_id,
        ]);
    }

    /** Never overwrites a successful refund. Reads the row again, so it also works after a rollback. */
    public function markRefundFailed(Transaction $transaction): void
    {
        $payment = Payment::where('transaction_id', $transaction->id)->latest('id')->first();

        if ($payment === null || $payment->refund_status === 'success') {
            return;
        }

        $payment->update(['refund_status' => 'failed']);
    }

    private function isPaid(array $remote): bool
    {
        return $remote['transaction_status'] === 'settlement'
            || ($remote['transaction_status'] === 'capture' && ($remote['fraud_status'] ?? null) === 'accept');
    }
}
