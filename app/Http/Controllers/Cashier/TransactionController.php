<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function receipt(Transaction $transaction): View
    {
        return view('cashier.receipt', [
            'transaction' => $transaction->load('details.product', 'payment', 'cashier'),
        ]);
    }
}
