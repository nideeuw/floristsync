<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\TransactionHistoryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function __construct(private TransactionHistoryService $history) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in([Transaction::TYPE_POS, Transaction::TYPE_ONLINE])],
            'status' => ['nullable', Rule::in(array_keys($this->history->statuses()))],
            'method' => ['nullable', 'string', 'max:50'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return view('admin.transactions.index', [
            'transactions' => $this->history->paginate($filters),
            'statuses' => $this->history->statuses(),
            'methods' => $this->history->paymentMethods(),
        ]);
    }

    public function show(Transaction $transaction): View
    {
        $transaction->load(['details.product', 'payments', 'user', 'cashier', 'shift', 'cancelledBy']);

        return view('admin.transactions.show', ['transaction' => $transaction]);
    }
}
