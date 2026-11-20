<?php

namespace App\Observers;

use App\Models\Transaction;
use App\Services\AuditService;

class TransactionObserver
{
    /** Writes one audit row whenever the order status really changed. */
    public function updated(Transaction $transaction): void
    {
        if (! $transaction->wasChanged('status')) {
            return;
        }

        app(AuditService::class)->log(
            'order.status_changed',
            'transaction',
            $transaction->id,
            ['status' => $transaction->getOriginal('status')],
            ['status' => $transaction->status],
        );
    }
}
