<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class PosCancellationService
{
    public function __construct(
        private InventoryService $inventory,
    ) {}

    public function cancel(Transaction $transaction, User $cashier, string $reason): Transaction
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new DomainException('Cancellation reason is required.');
        }

        DB::transaction(function () use ($transaction, $cashier, $reason) {
            $locked = Transaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();

            if ($locked->transaction_type !== Transaction::TYPE_POS) {
                throw new DomainException('Only POS transactions can be cancelled.');
            }

            if ($locked->status !== Transaction::COMPLETED) {
                if ($locked->status === Transaction::CANCELLED) {
                    throw new DomainException('This transaction has already been cancelled.');
                }

                throw new DomainException('Only completed POS transactions can be cancelled.');
            }

            foreach ($locked->details()->with('product')->get() as $detail) {
                if ($detail->product) {
                    $this->inventory->add(
                        $detail->product,
                        $detail->qty,
                        'cancel',
                        "POS transaction #{$locked->id} cancelled"
                    );
                }
            }

            $locked->update([
                'status' => Transaction::CANCELLED,
                'cancel_reason' => $reason,
                'cancelled_by' => $cashier->id,
                'cancelled_at' => now(),
            ]);
        });

        return $transaction->refresh();
    }
}
