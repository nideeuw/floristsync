<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use LogicException;

class PosCancellationService
{
    public function cancel(Transaction $transaction, User $cashier, string $reason): Transaction
    {
        throw new LogicException('Not implemented yet.');
    }
}
