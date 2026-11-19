<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\MidtransService;
use App\Services\TransactionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExpirePendingOrders extends Command
{
    private const FAILED_STATUSES = ['deny', 'cancel', 'expire', 'failure'];

    protected $signature = 'orders:expire-pending';

    protected $description = 'Syncs online orders that are still waiting for payment after the payment window with Midtrans, and cancels the ones that were never paid';

    public function handle(TransactionService $transactions, MidtransService $midtrans): int
    {
        $orders = Transaction::query()
            ->where('transaction_type', Transaction::TYPE_ONLINE)
            ->where('status', Transaction::PENDING_PAYMENT)
            ->where('created_on', '<', now()->subMinutes(Transaction::PAYMENT_WINDOW_MINUTES))
            ->with('payment')
            ->orderBy('id')
            ->get();

        $expired = 0; // cancelled here: Midtrans has no record, or the invoice is still pending
        $synced = 0;  // Midtrans status applied through updatePaymentStatus (paid or failed)
        $failed = 0;  // an error happened for this order, it is retried on the next run

        foreach ($orders as $transaction) {
            try {
                $orderId = $transaction->payment?->midtrans_order_id;
                $remote = $orderId ? $midtrans->getStatus($orderId) : null;

                // getStatus() returns null on HTTP 404, Midtrans can also answer 200 with status_code 404 in the body.
                $hasRecord = $remote !== null && (string) ($remote['status_code'] ?? '') !== '404';
                $status = $hasRecord ? ($remote['transaction_status'] ?? null) : null;

                if (! $hasRecord || $status === 'pending') {
                    $transactions->cancel($transaction);

                    if ($transaction->status === Transaction::CANCELLED) {
                        Log::info("Expire pending orders: cancelled order #{$transaction->id}");
                        $expired++;
                    }

                    continue;
                }

                if ($this->isPaid($remote) || in_array($status, self::FAILED_STATUSES, true)) {
                    // Same fields that TransactionService::resumePayment() builds.
                    $transactions->updatePaymentStatus([
                        'order_id' => $orderId,
                        'transaction_status' => $status,
                        'fraud_status' => $remote['fraud_status'] ?? null,
                        'payment_type' => $remote['payment_type'] ?? null,
                        'gross_amount' => $remote['gross_amount'] ?? null,
                    ]);

                    Log::info("Expire pending orders: synced order #{$transaction->id} with Midtrans status '{$status}'");
                    $synced++;

                    continue;
                }

                // authorize, capture with a fraud challenge, or anything unexpected: money may be held, so do not cancel.
                Log::warning("Expire pending orders: order #{$transaction->id} left untouched, Midtrans status is '{$status}'");
            } catch (Throwable $e) {
                $failed++;
                Log::warning("Expire pending orders: order #{$transaction->id} failed: {$e->getMessage()}");
            }
        }

        $summary = sprintf('Expire pending orders: expired %d, synced %d, failed %d', $expired, $synced, $failed);

        Log::info($summary);
        $this->info($summary);

        return self::SUCCESS;
    }

    private function isPaid(array $remote): bool
    {
        $status = $remote['transaction_status'] ?? '';

        return $status === 'settlement'
            || ($status === 'capture' && ($remote['fraud_status'] ?? null) === 'accept');
    }
}