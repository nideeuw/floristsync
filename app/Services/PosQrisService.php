<?php

namespace App\Services;

use App\Models\Transaction;
use LogicException;

/**
 * Stub: QRIS payment for POS sales through Midtrans (the QR code is shown on the cashier screen).
 *
 * Planned flow:
 * 1. The cashier chooses QRIS: the POS transaction is created as pending_payment
 *    (cash and debit complete immediately because the cashier only records them).
 * 2. createQr() asks Midtrans for a QRIS code and returns the data the POS screen needs to draw it.
 * 3. The Midtrans webhook (or status() polling) marks the payment as settled and the transaction as completed.
 *    The webhook must not send the "New paid order" notification for POS sales.
 */
class PosQrisService
{
    /** Data the POS screen needs to draw the QR code. */
    public function createQr(Transaction $transaction): array
    {
        throw new LogicException('Not implemented yet.');
    }

    /** Current Midtrans payment status of a POS transaction (pending, settlement, expire, ...). */
    public function status(Transaction $transaction): string
    {
        throw new LogicException('Not implemented yet.');
    }
}
