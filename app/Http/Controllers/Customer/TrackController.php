<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
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
}
