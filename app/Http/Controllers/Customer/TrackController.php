<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\SettingService;
use App\Services\ShippingService;
use App\Services\TransactionService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class TrackController extends Controller
{
    public function index(Request $request, ShippingService $shipping): View
    {
        $own = Transaction::with('details.product', 'payment')
            ->where('user_id', $request->user()->id)
            ->where('transaction_type', Transaction::TYPE_ONLINE);

        $orders = (clone $own)->latest('created_on')->paginate(10)->withQueryString();

        $orderNumber = trim((string) $request->query('order', ''));
        $selected = null;
        $tracking = null;

        if ($orderNumber !== '') {
            $selected = (clone $own)
                ->whereOrderNumber($orderNumber)
                ->first();
        }

        if ($selected && $selected->tracking_number && $selected->courier) {
            try {
                $tracking = $shipping->trackWaybill($selected->tracking_number, $selected->courier, $selected->shipping_phone);
            } catch (Throwable) {
                $tracking = null;
            }
        }

        return view('customer.track', [
            'orders' => $orders,
            'orderNumber' => $orderNumber,
            'selected' => $selected,
            'tracking' => $tracking,
        ]);
    }

    public function pay(Request $request, Transaction $transaction, TransactionService $transactions): View|RedirectResponse
    {
        abort_unless($transaction->user_id === $request->user()?->id, 404);
        abort_unless($transaction->transaction_type === Transaction::TYPE_ONLINE, 404);

        if ($transaction->status !== Transaction::PENDING_PAYMENT) {
            return redirect()->route('track')->with('error', 'This order is not waiting for payment.');
        }

        try {
            $transaction = $transactions->resumePayment($transaction);
        } catch (DomainException|RuntimeException $e) {
            return redirect()->route('track')->with('error', $e->getMessage());
        }

        if ($transaction->status !== Transaction::PENDING_PAYMENT) {
            $paid = $transaction->status !== Transaction::CANCELLED;

            return redirect()
                ->route('track', ['order' => $transaction->order_number])
                ->with(
                    $paid ? 'success' : 'error',
                    $paid
                        ? 'Your payment was received. Your order is now being processed.'
                        : 'The payment for this order was cancelled or has expired.'
                );
        }

        return view('customer.payment', [
            'transaction' => $transaction,
            'snapToken' => $transaction->payment?->snap_token,
            'initialState' => app(\App\Services\CustomerPaymentService::class)->getInitialState($transaction),
            'clientKey' => config('services.midtrans.client_key'),
            'isProduction' => (bool) config('services.midtrans.is_production'),
        ]);
    }

    public function received(Request $request, Transaction $transaction, TransactionService $transactions): RedirectResponse
    {
        abort_unless($transaction->user_id === $request->user()?->id, 404);
        abort_unless($transaction->transaction_type === Transaction::TYPE_ONLINE, 404);

        try {
            $transactions->confirmReceived($transaction, $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('track', ['order' => $transaction->order_number])
            ->with('success', 'Thank you! Your order is now completed.');
    }

    /** Sends the invoice email again. At most 3 times a day for each order. */
    public function resendInvoice(Request $request, Transaction $transaction): RedirectResponse
    {
        abort_unless($transaction->user_id === $request->user()?->id, 404);
        abort_unless($transaction->transaction_type === Transaction::TYPE_ONLINE, 404);

        $paid = [Transaction::PROCESSING, Transaction::READY_FOR_PICKUP, Transaction::SHIPPED, Transaction::COMPLETED];

        if (! in_array($transaction->status, $paid, true)) {
            return back()->with('error', 'The invoice is only available for paid orders.');
        }

        $allowed = \Illuminate\Support\Facades\RateLimiter::attempt('invoice-resend:'.$transaction->id, 3, fn () => true, 86400);

        if (! $allowed) {
            return back()->with('error', 'You can resend the invoice 3 times a day for each order. Please try again tomorrow.');
        }

        \App\Jobs\SendInvoiceMail::dispatch($transaction->id, true)->afterResponse();

        return back()->with('success', "The invoice will be sent to {$request->user()->email} in a moment.");
    }

    public function invoice(Request $request, Transaction $transaction, SettingService $settings): View
    {
        $user = $request->user();

        abort_unless($transaction->user_id === $user?->id, 404);
        abort_unless($transaction->transaction_type === Transaction::TYPE_ONLINE, 404);

        $transaction->loadMissing('details.product', 'payment', 'user');

        $isCancelled = $transaction->status === Transaction::CANCELLED || $transaction->payment?->payment_status === 'cancel';
        $isPending = $transaction->status === Transaction::PENDING_PAYMENT || in_array($transaction->payment?->payment_status, ['pending', null], true);
        $isValid = ! $isCancelled && ! $isPending;

        $message = null;
        if (! $isValid) {
            if ($isCancelled) {
                $message = 'This order was cancelled. Invoice cannot be printed.';
            } else {
                $message = 'This order is pending payment. Invoice will be available once payment is completed.';
            }
        }

        return view('customer.invoice', [
            'transaction' => $transaction,
            'isValid' => $isValid,
            'message' => $message,
            'storeName' => $settings->get('store_name', config('app.name', 'FloristSync')),
            'storeAddress' => $settings->get('address', config('floristsync.store_address')),
            'storePhone' => $settings->get('phone', config('floristsync.phone')),
            'storeWhatsapp' => $settings->whatsappNumber(),
        ]);
    }
}
