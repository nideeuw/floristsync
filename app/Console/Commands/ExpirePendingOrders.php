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
    protected $signature = 'orders:expire-pending';

    protected $description = 'Cancels online orders that are still waiting for payment after the payment window';

    public function handle(TransactionService $transactions, MidtransService $midtrans): int
    {
        $orders = Transaction::query()
            ->where('transaction_type', Transaction::TYPE_ONLINE)
            ->where('status', Transaction::PENDING_PAYMENT)
            ->where('created_on', '<', now()->subMinutes(Transaction::PAYMENT_WINDOW_MINUTES))
            ->with('payment')
            ->orderBy('id')
            ->get();

        $paid = 0;
        $cancelled = 0;

        foreach ($orders as $transaction) {
            try {
                $orderId = $transaction->payment?->midtrans_order_id;

                if ($orderId && $this->isPaid($midtrans->getStatus($orderId))) {
                    $paid++;

                    continue;
                }

                $transactions->cancel($transaction);

                Log::info("Expire pending orders: cancelled order #{$transaction->id}");

                $cancelled++;
            } catch (Throwable $e) {
                Log::warning("Expire pending orders: order #{$transaction->id} failed: {$e->getMessage()}");
            }
        }

        $this->info(sprintf('Checked %d order(s): %d already paid, %d cancelled.', $orders->count(), $paid, $cancelled));

        return self::SUCCESS;
    }

    private function isPaid(?array $remote): bool
    {
        if ($remote === null) {
            return false;
        }

        return ($remote['transaction_status'] ?? '') === 'settlement'
            || (($remote['transaction_status'] ?? '') === 'capture' && ($remote['fraud_status'] ?? null) === 'accept');
    }
}
