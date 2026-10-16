<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class PaymentResumeService
{
    private const CLOSED_STATUSES = ['deny', 'cancel', 'expire', 'failure'];

    public function __construct(private MidtransService $midtrans) {}

    public function deadline(Transaction $transaction): Carbon
    {
        return Carbon::parse($transaction->created_on)->addMinutes(MidtransService::PAYMENT_WINDOW_MINUTES);
    }

    public function canResume(Transaction $transaction): bool
    {
        return $transaction->transaction_type === Transaction::TYPE_ONLINE
            && $transaction->status === Transaction::PENDING_PAYMENT
            && $this->deadline($transaction)->isFuture();
    }

        /**
     * Snap token to reopen the payment page. Reuses the stored token while the order is inside
     * the payment window and Midtrans has no finished payment for it.
     *
     * @throws DomainException|RuntimeException
     */
    public function snapTokenFor(Transaction $transaction, User $member): string
    {
        
        if ($transaction->status !== Transaction::PENDING_PAYMENT) {
            throw new DomainException('This order is no longer waiting for payment.');
        }

        if (! $this->canResume($transaction)) {
            throw new DomainException('The payment time for this order has run out.');
        }

        $transaction->loadMissing('details.product', 'payment');
        $payment = $transaction->payment;

        if (! $payment || ! $payment->midtrans_order_id) {
            throw new DomainException('This order has no payment to continue.');
        }

        $this->assertStillPayable($payment->midtrans_order_id);

        if ($payment->snap_token) {
            return $payment->snap_token;
        }

        try {
            $token = $this->midtrans->createSnapToken($transaction, [
                'name' => $member->name,
                'email' => $member->email,
                'phone' => $member->phone,
            ]);
        } catch (Throwable $e) {
            Log::error("Snap token failed when resuming payment for order #{$transaction->id}: {$e->getMessage()}");

            throw new RuntimeException('Payment could not be started right now. Please try again.', 0, $e);
        }

        $payment->update(['snap_token' => $token]);

        return $token;
    }

    private function assertStillPayable(string $orderId): void
    {
        $remote = $this->midtrans->getStatus($orderId);

        if ($remote === null) {
            return;
        }

        $status = $remote['transaction_status'] ?? '';

        $paid = $status === 'settlement'
            || ($status === 'capture' && ($remote['fraud_status'] ?? null) === 'accept');

        if ($paid) {
            throw new DomainException('Your payment was received and is being confirmed. Please check again in a moment.');
        }

        if (in_array($status, self::CLOSED_STATUSES, true)) {
            throw new DomainException('This payment is closed and can no longer be paid. Please place a new order or contact us via WhatsApp.');
        }
    }
}