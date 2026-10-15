<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\PosCancellationService;
use App\Services\ShiftService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class CancelTransactionController extends Controller
{
    public function __construct(
        private PosCancellationService $cancellationService,
        private ShiftService $shifts,
    ) {}

    public function index(Request $request): View
    {
        $cashier = $request->user();
        $shift = $this->shifts->current($cashier);

        $transactions = Transaction::where('transaction_type', Transaction::TYPE_POS)
            ->where('cashier_id', $cashier->id)
            ->where('created_on', '>=', now()->startOfDay())
            ->with(['payment', 'details.product', 'cancelledBy'])
            ->withCount('details')
            ->latest('id')
            ->paginate(15);

        return view('cashier.cancel', [
            'transactions' => $transactions,
            'shift' => $shift,
        ]);
    }

    public function store(Request $request, Transaction $transaction): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        try {
            $this->cancellationService->cancel($transaction, $request->user(), $data['reason']);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Could not cancel the transaction. Please try again.');
        }

        return redirect()->route('cashier.cancel')
            ->with('success', "Transaction {$transaction->order_number} has been cancelled and stock has been returned.");
    }
}
