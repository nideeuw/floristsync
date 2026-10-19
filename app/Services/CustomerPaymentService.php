<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use DomainException;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CustomerPaymentService
{
    private const ALLOWED_METHODS = ['qris', 'gopay', 'shopeepay', 'credit_card'];

    public function __construct(
        private MidtransService $midtrans,
        private TransactionService $transactions,
        private PaymentReversalService $reversal,
    ) {}

    /**
     * Charge a specific payment method for an online order.
     *
     * @throws DomainException|RuntimeException
     */
    public function charge(Transaction $transaction, string $method, User $user): array
    {
        $this->assertCanPay($transaction, $user);

        $method = $method === 'card' ? 'credit_card' : $method;

        if (! in_array($method, self::ALLOWED_METHODS, true)) {
            throw new DomainException("Unsupported payment method: {$method}");
        }

        $payment = $transaction->payment;
        if (! $payment) {
            throw new DomainException('Payment record not found for this order.');
        }

        // If changing to a different payment method, cancel previous active invoice on Midtrans first
        if ($payment->payment_method && $payment->payment_method !== $method) {
            $this->reversal->cancelInvoice($transaction);

            $newOrderId = 'FS-'.$transaction->id.'-'.strtoupper(Str::random(6));
            $payment->update([
                'midtrans_order_id' => $newOrderId,
                'payment_method' => $method,
                'payment_status' => 'pending',
                'snap_token' => null,
                'payment_proof' => null,
            ]);
            $payment->refresh();
        }

        // Return cached charge data if same method is already pending and available
        if ($payment->payment_method === $method && $payment->payment_status === 'pending') {
            $cached = $this->getCachedChargeData($transaction, $method);
            if ($cached !== null) {
                return $cached;
            }
        }

        $customer = [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->getAttribute('phone'),
        ];

        if ($method === 'credit_card') {
            return $this->chargeCard($transaction, $customer);
        }

        return $this->chargeCoreMethod($transaction, $method, $customer);
    }

    /**
     * Cancel the active charge on Midtrans and reset payment method to allow choosing another.
     */
    public function cancelCharge(Transaction $transaction, User $user): void
    {
        $this->assertCanPay($transaction, $user);

        $payment = $transaction->payment;
        if (! $payment || ! $payment->midtrans_order_id) {
            return;
        }

        $this->reversal->cancelInvoice($transaction);

        $newOrderId = 'FS-'.$transaction->id.'-'.strtoupper(Str::random(6));
        $payment->update([
            'midtrans_order_id' => $newOrderId,
            'payment_method' => null,
            'payment_status' => 'pending',
            'snap_token' => null,
            'payment_proof' => null,
        ]);
    }

    /**
     * Poll the current payment status directly from Midtrans for this order.
     */
    public function checkStatus(Transaction $transaction, User $user): array
    {
        abort_unless($transaction->user_id === $user->id, 403, 'Unauthorized.');

        $transaction->refresh();
        $transaction->loadMissing('payment');

        if (in_array($transaction->status, [Transaction::PROCESSING, Transaction::COMPLETED], true)
            || $transaction->payment?->payment_date !== null) {
            return [
                'status' => 'settlement',
                'transaction_status' => $transaction->status,
                'is_paid' => true,
                'is_failed' => false,
                'redirect_url' => route('track', ['order' => $transaction->order_number]),
            ];
        }

        if ($transaction->status === Transaction::CANCELLED) {
            return [
                'status' => $transaction->payment?->payment_status ?? 'cancel',
                'transaction_status' => Transaction::CANCELLED,
                'is_paid' => false,
                'is_failed' => true,
                'redirect_url' => null,
            ];
        }

        $payment = $transaction->payment;
        if ($payment && $payment->midtrans_order_id) {
            $remote = $this->midtrans->getStatus($payment->midtrans_order_id);
            if ($remote !== null) {
                $this->transactions->updatePaymentStatus($remote);
                $transaction->refresh();
                $transaction->loadMissing('payment');
            }
        }

        $isPaid = in_array($transaction->status, [Transaction::PROCESSING, Transaction::COMPLETED], true)
            || $transaction->payment?->payment_date !== null;

        $isFailed = $transaction->status === Transaction::CANCELLED
            || in_array($transaction->payment?->payment_status, ['deny', 'cancel', 'expire', 'failure'], true);

        return [
            'status' => $transaction->payment?->payment_status ?? 'pending',
            'transaction_status' => $transaction->status,
            'is_paid' => $isPaid,
            'is_failed' => $isFailed,
            'redirect_url' => $isPaid ? route('track', ['order' => $transaction->order_number]) : null,
        ];
    }

    /**
     * Get initial state to pass to the Blade view for seamless Alpine initialization.
     */
    public function getInitialState(Transaction $transaction): array
    {
        $transaction->loadMissing('payment');
        $payment = $transaction->payment;
        $remainingSeconds = max(0, (int) now()->diffInSeconds($transaction->paymentDeadline(), false));

        return [
            'method' => $payment?->payment_method,
            'qr_url' => in_array($payment?->payment_method, ['qris', 'gopay'], true) ? $payment?->snap_token : null,
            'deeplink' => in_array($payment?->payment_method, ['gopay', 'shopeepay'], true) ? $payment?->payment_proof : null,
            'qr_string' => $payment?->payment_method === 'qris' ? $payment?->payment_proof : null,
            'snap_token' => $payment?->payment_method === 'credit_card' ? $payment?->snap_token : null,
            'remaining_seconds' => $remainingSeconds,
        ];
    }

    private function chargeCard(Transaction $transaction, array $customer): array
    {
        $payment = $transaction->payment;

        try {
            $token = $this->midtrans->createSnapToken($transaction, $customer, ['credit_card']);
        } catch (Throwable $e) {
            throw new RuntimeException('Payment could not be started right now. Please try again.', 0, $e);
        }

        $payment->update([
            'payment_method' => 'credit_card',
            'snap_token' => $token,
            'payment_status' => 'pending',
        ]);

        return [
            'method' => 'credit_card',
            'snap_token' => $token,
            'order_id' => (string) $payment->midtrans_order_id,
            'gross_amount' => (int) $transaction->total,
            'expires_at' => $transaction->paymentDeadline()?->toIso8601String(),
        ];
    }

    private function chargeCoreMethod(Transaction $transaction, string $method, array $customer): array
    {
        $payment = $transaction->payment;
        $response = $this->midtrans->chargeCoreApi($transaction, $method, $customer);

        $actions = $response['actions'] ?? [];
        $qrUrl = null;
        $deeplink = null;

        foreach ($actions as $action) {
            $action = (array) $action;
            $name = $action['name'] ?? '';
            if ($name === 'generate-qr-code') {
                $qrUrl = $action['url'] ?? null;
            } elseif ($name === 'deeplink-redirect') {
                $deeplink = $action['url'] ?? null;
            }
        }

        $qrString = $response['qr_string'] ?? null;

        if ($method === 'qris') {
            $payment->update([
                'payment_method' => 'qris',
                'snap_token' => $qrUrl,
                'payment_proof' => $qrString,
                'payment_status' => 'pending',
            ]);

            return [
                'method' => 'qris',
                'qr_url' => $qrUrl,
                'qr_string' => $qrString,
                'order_id' => (string) $payment->midtrans_order_id,
                'gross_amount' => (int) $transaction->total,
                'expires_at' => $transaction->paymentDeadline()?->toIso8601String(),
            ];
        }

        if ($method === 'gopay') {
            $payment->update([
                'payment_method' => 'gopay',
                'snap_token' => $qrUrl,
                'payment_proof' => $deeplink ?? $qrString,
                'payment_status' => 'pending',
            ]);

            return [
                'method' => 'gopay',
                'qr_url' => $qrUrl,
                'deeplink' => $deeplink,
                'qr_string' => $qrString,
                'order_id' => (string) $payment->midtrans_order_id,
                'gross_amount' => (int) $transaction->total,
                'expires_at' => $transaction->paymentDeadline()?->toIso8601String(),
            ];
        }

        if ($method === 'shopeepay') {
            $payment->update([
                'payment_method' => 'shopeepay',
                'snap_token' => null,
                'payment_proof' => $deeplink,
                'payment_status' => 'pending',
            ]);

            return [
                'method' => 'shopeepay',
                'deeplink' => $deeplink,
                'order_id' => (string) $payment->midtrans_order_id,
                'gross_amount' => (int) $transaction->total,
                'expires_at' => $transaction->paymentDeadline()?->toIso8601String(),
            ];
        }

        throw new DomainException("Unsupported Core API method: {$method}");
    }

    private function getCachedChargeData(Transaction $transaction, string $method): ?array
    {
        $payment = $transaction->payment;
        if (! $payment) {
            return null;
        }

        $base = [
            'order_id' => (string) $payment->midtrans_order_id,
            'gross_amount' => (int) $transaction->total,
            'expires_at' => $transaction->paymentDeadline()?->toIso8601String(),
        ];

        if ($method === 'credit_card' && $payment->snap_token) {
            return array_merge($base, [
                'method' => 'credit_card',
                'snap_token' => $payment->snap_token,
            ]);
        }

        if ($method === 'qris' && $payment->snap_token) {
            return array_merge($base, [
                'method' => 'qris',
                'qr_url' => $payment->snap_token,
                'qr_string' => $payment->payment_proof,
            ]);
        }

        if ($method === 'gopay' && ($payment->snap_token || $payment->payment_proof)) {
            return array_merge($base, [
                'method' => 'gopay',
                'qr_url' => $payment->snap_token,
                'deeplink' => $payment->payment_proof,
                'qr_string' => null,
            ]);
        }

        if ($method === 'shopeepay' && $payment->payment_proof) {
            return array_merge($base, [
                'method' => 'shopeepay',
                'deeplink' => $payment->payment_proof,
            ]);
        }

        return null;
    }

    private function assertCanPay(Transaction $transaction, User $user): void
    {
        abort_unless($transaction->user_id === $user->id, 403, 'Unauthorized.');
        abort_unless($transaction->transaction_type === Transaction::TYPE_ONLINE, 404);

        if ($transaction->status !== Transaction::PENDING_PAYMENT) {
            throw new DomainException('This order is no longer waiting for payment.');
        }

        if (! $transaction->canResumePayment()) {
            throw new DomainException('The payment time for this order has expired. Please place a new order.');
        }
    }
}
