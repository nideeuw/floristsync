<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\TransactionService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(private TransactionService $service) {}

    /** Online orders only. POS sales are completed at the cashier terminal. */
    public function index(Request $request): View
    {
        $online = fn() => Transaction::where('transaction_type', Transaction::TYPE_ONLINE);

        $counts = $online()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $orders = $online()
            ->with('payment', 'user:id,name')
            ->when($request->query('status'), fn($q, $v) => $q->where('status', $v))
            ->when($request->query('fulfillment') === 'delivery', fn($q) => $q->whereNotNull('courier'))
            ->when($request->query('fulfillment') === 'pickup', fn($q) => $q->whereNull('courier'))
            ->when($request->query('q'), fn($q, $v) => $q->where(function ($w) use ($v) {
                $w->whereHas('payments', fn($p) => $p->where('midtrans_order_id', 'like', "%{$v}%"));

                if (ctype_digit($v)) {
                    $w->orWhere('id', (int) $v);
                }
            }))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.orders.index', compact('orders', 'counts'));
    }

    public function show(Transaction $transaction): View
    {
        $this->ensureOnline($transaction);

        $transaction->load('details.product', 'payment', 'user:id,name,email,phone');

        return view('admin.orders.show', compact('transaction'));
    }

    /** Pickup flow: processing -> ready for pickup -> completed. Delivery: shipped -> completed. */
    public function updateStatus(Request $request, Transaction $transaction): RedirectResponse
    {
        $this->ensureOnline($transaction);

        $allowed = match (true) {
            $transaction->status === Transaction::PROCESSING && ! $transaction->isDelivery() => [Transaction::READY_FOR_PICKUP],
            in_array($transaction->status, [Transaction::READY_FOR_PICKUP, Transaction::SHIPPED], true) => [Transaction::COMPLETED],
            default => [],
        };

        $data = $request->validate(['status' => ['required', Rule::in($allowed)]]);

        try {
            $this->service->changeStatus($transaction, $data['status']);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Order status updated.');
    }

    /** Delivery flow: enter the courier tracking number (resi). The order becomes "shipped". */
    public function updateTrackingNumber(Request $request, Transaction $transaction): RedirectResponse
    {
        $this->ensureOnline($transaction);

        $data = $request->validate(['tracking_number' => ['required', 'string', 'max:50']]);

        try {
            $this->service->inputTrackingNumber($transaction, $data['tracking_number']);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Tracking number saved. Order marked as shipped.');
    }

    private function ensureOnline(Transaction $transaction): void
    {
        abort_unless($transaction->transaction_type === Transaction::TYPE_ONLINE, 404);
    }
}
