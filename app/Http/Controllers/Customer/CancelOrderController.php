<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\OrderCancellationService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class CancelOrderController extends Controller
{
    public function __construct(private OrderCancellationService $cancellations) {}

    public function store(Request $request, Transaction $transaction): RedirectResponse
    {
        abort_unless((int) $transaction->user_id === (int) $request->user()->id, 404);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        try {
            $result = $this->cancellations->request($transaction, $request->user(), $data['reason']);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'We could not cancel your order right now. Please try again later.');
        }

        $message = $result->status === Transaction::CANCELLED
            ? "Order #{$result->id} has been cancelled."
            : "Your cancellation request for order #{$result->id} has been sent. We will review it soon.";

        return redirect()->route('track')->with('success', $message);
    }
}
