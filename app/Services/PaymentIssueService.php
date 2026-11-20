<?php

namespace App\Services;

use App\Models\Transaction;
use DomainException;
use Illuminate\Support\Collection;

class PaymentIssueService
{
    private const LIMIT = 50;

    // A card payment that is still only authorized or captured after this many hours should have settled.
    private const UNSETTLED_AFTER_HOURS = 24;

    public function __construct(
        private MidtransService $midtrans,
        private TransactionService $transactions,
    ) {}

    /** @return array{pending: Collection, late_paid: Collection, failed_refunds: Collection, unconfirmed: Collection} */
    public function sections(): array
    {
        $online = fn () => Transaction::query()
            ->where('transaction_type', Transaction::TYPE_ONLINE)
            ->with('payment', 'user:id,name');

        return [
            // Waiting for payment longer than the payment window: the webhook or the expire job may have been missed.
            'pending' => $online()
                ->where('status', Transaction::PENDING_PAYMENT)
                ->where('created_on', '<', now()->subMinutes(Transaction::PAYMENT_WINDOW_MINUTES))
                ->orderBy('id')
                ->limit(self::LIMIT)
                ->get(),

            // Paid after the order was cancelled and not refunded yet.
            'late_paid' => $online()
                ->where('status', Transaction::CANCELLED)
                ->whereHas('payments', fn ($q) => $q
                    ->whereIn('payment_status', ['settlement', 'capture'])
                    ->where(fn ($r) => $r->whereNull('refund_status')->orWhere('refund_status', 'pending')))
                ->orderBy('id')
                ->limit(self::LIMIT)
                ->get(),

            'failed_refunds' => $online()
                ->whereHas('payments', fn ($q) => $q->where('refund_status', 'failed'))
                ->orderBy('id')
                ->limit(self::LIMIT)
                ->get(),

            // Still authorized or captured long after payment: Midtrans probably settled it and the webhook was missed.
            'unconfirmed' => $online()
                ->whereNotIn('status', [Transaction::CANCELLED, Transaction::PENDING_PAYMENT])
                ->whereHas('payments', fn ($q) => $q
                    ->whereIn('payment_status', ['authorize', 'capture'])
                    ->where('payment_date', '<', now()->subHours(self::UNSETTLED_AFTER_HOURS)))
                ->orderBy('id')
                ->limit(self::LIMIT)
                ->get(),
        ];
    }

    /** Asks Midtrans and applies the answer through the same path as the webhook. Returns a message for the admin. */
    public function recheck(Transaction $transaction): string
    {
        $payment = $transaction->payment;

        if (! $payment || ! $payment->midtrans_order_id) {
            throw new DomainException('This order has no Midtrans payment to check.');
        }

        $orderId = $payment->midtrans_order_id;
        $remote = $this->midtrans->getStatus($orderId);

        if ($remote === null || (string) ($remote['status_code'] ?? '') === '404') {
            return 'Midtrans has no record of this payment.';
        }

        $beforeOrder = $transaction->status;
        $beforePayment = $payment->payment_status;

        // Same fields that the webhook, the expire job and resumePayment() build.
        $this->transactions->updatePaymentStatus([
            'order_id' => $orderId,
            'transaction_status' => $remote['transaction_status'] ?? null,
            'fraud_status' => $remote['fraud_status'] ?? null,
            'payment_type' => $remote['payment_type'] ?? null,
            'gross_amount' => $remote['gross_amount'] ?? null,
            'refund_amount' => $remote['refund_amount'] ?? null,
        ]);

        $transaction->refresh();
        $payment->refresh();

        app(AuditService::class)->logChanges(
            'payment.rechecked',
            'transaction',
            $transaction->id,
            ['payment_status' => $beforePayment],
            ['payment_status' => $payment->payment_status],
        );

        $remoteStatus = $remote['transaction_status'] ?? 'unknown';

        if ($beforeOrder === $transaction->status && $beforePayment === $payment->payment_status) {
            return $remoteStatus === 'pending'
                ? 'Midtrans still shows this payment as pending. Unpaid orders are cancelled by the expire job.'
                : "Already in sync with Midtrans (status: {$remoteStatus}).";
        }

        return "Updated from Midtrans: payment {$beforePayment} to {$payment->payment_status}, order {$beforeOrder} to {$transaction->status}.";
    }
}
