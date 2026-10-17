<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\MidtransService;
use App\Services\PaymentReversalService;
use App\Services\TransactionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExpirePendingOrders extends Command
{
    protected $signature = 'orders:expire-pending';

    protected $description = 'Safety net that expires online orders still waiting for payment after the payment window';

    public function handle(
        TransactionService $transactions,
        MidtransService $midtrans,
        PaymentReversalService $reversal,
    ): int {
        $orders = Transaction::query()
            ->where('transaction_type', Transaction::TYPE_ONLINE)
            ->where('status', Transaction::PENDING_PAYMENT)
            ->where('created_on', '<', now()->subMinutes(MidtransService::PAYMENT_WINDOW_MINUTES))
            ->with('payment')
            ->orderBy('id')
            ->get();

        $paid = 0;
        $cancelled = 0;

        foreach ($orders as $transaction) {
            try {
                $orderId = $transaction->payment?->midtrans_order_id;

                if (! $orderId) {
                    Log::warning("Expire pending orders: order #{$transaction->id} has no Midtrans order id.");

                    continue;
                }

                $remote = $midtrans->getStatus($orderId);

                if ($remote !== null && $transactions->isPaid($remote)) {
                    $transactions->updatePaymentStatus($remote);

                    $paid++;

                    continue;
                }

                $reversal->cancelInvoice($transaction);
                $transactions->cancel($transaction);

                $cancelled++;
            } catch (Throwable $e) {
                Log::warning("Expire pending orders: order #{$transaction->id} failed: ".$e->getMessage());
            }
        }

        $this->info(sprintf('Checked %d order(s): %d paid, %d cancelled.', $orders->count(), $paid, $cancelled));

        return self::SUCCESS;
    }
}
