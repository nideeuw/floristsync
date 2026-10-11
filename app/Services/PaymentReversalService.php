<?php

namespace App\Services;

use App\Models\Transaction;
use LogicException;

/**
 * Stub: reverse a Midtrans payment through the Midtrans API (no manual refund).
 * The Midtrans refund API supports card, GoPay, ShopeePay and QRIS payments only.
 */
class PaymentReversalService
{
    /** Cancel the invoice of an unpaid order so it can no longer be paid. */
    public function cancelInvoice(Transaction $transaction): void
    {
        throw new LogicException('Not implemented yet.');
    }

    /** Refund a paid order. Should throw a DomainException with a readable message when Midtrans rejects the refund. */
    public function refund(Transaction $transaction): void
    {
        throw new LogicException('Not implemented yet.');
    }
}
