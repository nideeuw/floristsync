<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function index(Request $request): View
    {
        $cashierId = $request->user()->id;
        $startOfDay = now()->startOfDay();

        $today = Transaction::where('transaction_type', Transaction::TYPE_POS)
            ->where('cashier_id', $cashierId)
            ->where('created_on', '>=', $startOfDay);

        $summary = (clone $today)
            ->successful()
            ->selectRaw('COUNT(*) as orders, COALESCE(SUM(total), 0) as revenue')
            ->first();

        $byMethod = DB::table('payments as p')
            ->join('transactions as t', 't.id', '=', 'p.transaction_id')
            ->where('t.transaction_type', Transaction::TYPE_POS)
            ->where('t.cashier_id', $cashierId)
            ->where('t.created_on', '>=', $startOfDay)
            ->whereNotIn('t.status', [Transaction::PENDING_PAYMENT, Transaction::CANCELLED])
            ->selectRaw('p.payment_method, COUNT(*) as orders, SUM(p.amount) as total')
            ->groupBy('p.payment_method')
            ->get()
            ->keyBy('payment_method');

        $transactions = (clone $today)
            ->with('payment')
            ->withCount('details')
            ->latest('id')
            ->paginate(15);

        return view('cashier.history', compact('summary', 'byMethod', 'transactions'));
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
