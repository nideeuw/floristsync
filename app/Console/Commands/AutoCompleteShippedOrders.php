<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class AutoCompleteShippedOrders extends Command
{
    protected $signature = 'orders:auto-complete-shipped';

    protected $description = 'Complete shipped delivery orders the member did not confirm within the configured days';

    public function handle(TransactionService $transactions): int
    {
        $days = (int) config('floristsync.auto_complete_shipped_days', 3);

        if ($days < 1) {
            $this->info('Auto-complete of shipped orders is turned off.');

            return self::SUCCESS;
        }

        $completed = 0;
        $failed = 0;

        Transaction::query()
            ->where('transaction_type', Transaction::TYPE_ONLINE)
            ->where('status', Transaction::SHIPPED)
            ->whereNotNull('courier')
            ->where('courier', '!=', '')
            ->whereNotNull('shipped_at')
            ->where('shipped_at', '<=', now()->subDays($days))
            ->chunkById(100, function ($orders) use ($transactions, &$completed, &$failed) {
                /** @var Transaction $order */
                foreach ($orders as $order) {
                    try {
                        $order->refresh();

                        if ($order->status !== Transaction::SHIPPED) {
                            continue;
                        }

                        $transactions->changeStatus($order, Transaction::COMPLETED);
                        $completed++;
                    } catch (Throwable $e) {
                        $failed++;
                        Log::error("Auto-complete of order #{$order->id} failed: {$e->getMessage()}");
                    }
                }
            });

        Log::info("Auto-complete shipped orders: {$completed} completed, {$failed} failed.", [
            'completed' => $completed,
            'failed' => $failed,
            'days' => $days,
        ]);

        $this->info("Completed: {$completed}, failed: {$failed}.");

        return self::SUCCESS;
    }
}
