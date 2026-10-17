<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\SettingService;
use App\Services\ShippingService;
use Illuminate\Http\Request;
use Illuminate\View\View;
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
                ->whereHas('payments', fn ($q) => $q->where('midtrans_order_id', $orderNumber))
                ->first();
        }

        if ($selected && $selected->tracking_number && $selected->courier) {
            try {
                $tracking = $shipping->trackWaybill($selected->tracking_number, $selected->courier);
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
