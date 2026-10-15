<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\ShiftService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function __construct(private ShiftService $shifts) {}

    public function index(Request $request): View
    {
        $cashier = $request->user();
        $shift = $this->shifts->current($cashier);
        $data = $this->shifts->getShiftSummary($shift, $cashier);

        return view('cashier.history', array_merge($data, ['shift' => $shift]));
    }

    public function receipt(Transaction $transaction): View
    {
        abort_unless(
            $transaction->transaction_type === Transaction::TYPE_POS
                && $transaction->status === Transaction::COMPLETED,
            404
        );

        return view('cashier.receipt', [
            'transaction' => $transaction->load('details.product', 'payment', 'cashier'),
        ]);
    }
}
