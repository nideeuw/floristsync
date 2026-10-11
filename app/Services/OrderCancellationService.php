<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use LogicException;

/**
 * Stub: cancellation flow for online orders.
 *
 * - pending_payment: cancel directly (cancel the Midtrans invoice, return the stock).
 * - processing: the member sends a request, an admin approves or rejects it.
 * - ready_for_pickup, shipped, completed: cannot be cancelled (the member contacts the store on WhatsApp).
 */
class OrderCancellationService
{
    /** Called by the member. Cancels directly or creates a pending request, depending on the order status. */
    public function request(Transaction $transaction, User $member, string $reason): Transaction
    {
        throw new LogicException('Not implemented yet.');
    }

    /** Called by an admin. Cancels the order, returns the stock and refunds the payment through the Midtrans API. */
    public function approve(Transaction $transaction, User $admin): Transaction
    {
        throw new LogicException('Not implemented yet.');
    }

    /** Called by an admin. Keeps the order as it is and marks the request as rejected. */
    public function reject(Transaction $transaction, User $admin): Transaction
    {
        throw new LogicException('Not implemented yet.');
    }
}
