<?php

namespace App\Services;

use App\Models\Transaction;
use DomainException;
use Illuminate\Support\Str;

/**
 * QRIS payment for POS sales through Midtrans (the QR code is shown on the cashier screen).
 */
class PosQrisService
{
    public function __construct(
        private MidtransService $midtrans,
        private TransactionService $transactions,
    ) {}

    /**
     * Data the POS screen needs to draw the QR code.
     *
     * @return array{qr_url: ?string, qr_string: ?string, order_id: string, gross_amount: int, actions: array}
     */
    public function createQr(Transaction $transaction): array
    {
        if ($transaction->transaction_type !== Transaction::TYPE_POS) {
            throw new DomainException('Only POS transactions can generate QRIS.');
        }

        if ($transaction->status !== Transaction::PENDING_PAYMENT) {
            throw new DomainException('Only pending transactions can generate QRIS.');
        }

        $payment = $transaction->payment;
        if (! $payment) {
            throw new DomainException('Payment record not found.');
        }

        // Return cached QR details if already generated
        if ($payment->snap_token) {
            return [
                'qr_url' => $payment->snap_token,
                'qr_string' => $payment->payment_proof,
                'order_id' => (string) $payment->midtrans_order_id,
                'gross_amount' => (int) $transaction->total,
                'actions' => [
                    [
                        'name' => 'generate-qr-code',
                        'method' => 'GET',
                        'url' => $payment->snap_token,
                    ],
                ],
            ];
        }

        if (! $payment->midtrans_order_id) {
            $payment->update([
                'midtrans_order_id' => 'FS-POS-'.$transaction->id.'-'.strtoupper(Str::random(6)),
            ]);
            $payment->refresh();
        }

        $response = $this->midtrans->chargeQris($transaction);

        $actions = $response['actions'] ?? [];
        $qrUrl = null;

        foreach ($actions as $action) {
            $action = (array) $action;
            if (($action['name'] ?? '') === 'generate-qr-code') {
                $qrUrl = $action['url'] ?? null;
                break;
            }
        }

        $qrString = $response['qr_string'] ?? null;

        $payment->update([
            'snap_token' => $qrUrl,
            'payment_proof' => $qrString,
        ]);

        return [
            'qr_url' => $qrUrl,
            'qr_string' => $qrString,
            'order_id' => (string) $payment->midtrans_order_id,
            'gross_amount' => (int) $transaction->total,
            'actions' => $actions,
        ];
    }

    /**
     * Current Midtrans payment status of a POS transaction (pending, settlement, expire, ...).
     */
    public function status(Transaction $transaction): string
    {
        $transaction->refresh();

        if ($transaction->status === Transaction::COMPLETED) {
            return 'settlement';
        }

        if ($transaction->status === Transaction::CANCELLED) {
            return $transaction->payment?->payment_status === 'expire' ? 'expire' : 'cancel';
        }

        $payment = $transaction->payment;
        if (! $payment || ! $payment->midtrans_order_id) {
            return 'pending';
        }

        $remote = $this->midtrans->getStatus($payment->midtrans_order_id);

        if (! $remote) {
            return 'pending';
        }

        $remoteStatus = $remote['transaction_status'] ?? 'pending';

        $this->transactions->updatePaymentStatus($remote);

        return $remoteStatus;
    }

    /**
     * Cancel a pending POS QRIS transaction and return stock.
     */
    public function cancel(Transaction $transaction): void
    {
        if ($transaction->status === Transaction::PENDING_PAYMENT) {
            $this->transactions->cancel($transaction);
        }
    }
}
