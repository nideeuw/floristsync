<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;

/**
 * Stub: QRIS payment of a POS transaction. Cashier only.
 * The real implementation must check that the transaction is a pending POS sale of the logged-in cashier.
 */
class PosQrisController extends Controller
{
    /** Create the QRIS code for a pending POS transaction. */
    public function store(Transaction $transaction): never
    {
        abort(501, 'Not implemented yet.');
    }

    /** Poll the payment status of the QRIS payment. */
    public function show(Transaction $transaction): never
    {
        abort(501, 'Not implemented yet.');
    }
}
