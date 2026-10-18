<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\OrderCancellationService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class OrderCancellationController extends Controller
{
    public function __construct(private OrderCancellationService $service) {}

    public function index(): View
    {
        return view('admin.orders.cancellations', [
            'requests' => $this->service->pendingRequests(),
        ]);
    }

    /** Refund, return the stock and cancel the order. A refund failure keeps the order untouched. */
    public function approve(Request $request, Transaction $transaction): RedirectResponse
    {
        try {
            $this->service->approve($transaction, $request->user());
        } catch (DomainException|RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Cancellation approved. The order was cancelled, the stock returned and the payment refunded.');
    }

    public function reject(Request $request, Transaction $transaction): RedirectResponse
    {
        try {
            $this->service->reject($transaction, $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Cancellation request rejected. The order stays in processing.');
    }

    public function cancel(Request $request, Transaction $transaction): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        try {
            $this->service->cancel($transaction, $request->user(), $data['reason']);
        } catch (DomainException|RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Order cancelled. The stock was returned and payment refunded.');
    }
}
