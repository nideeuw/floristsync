<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\ShippingService;
use App\Services\TransactionService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class OrderController extends Controller
{
    public function __construct(private TransactionService $service) {}

    /** Staff: list all orders. */
    public function index(Request $request): JsonResponse
    {
        $orders = Transaction::with('payment')
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('type'), fn ($q, $v) => $q->where('transaction_type', $v))
            ->latest('created_on')
            ->paginate(min($request->integer('per_page', 15), 50));

        return response()->json($orders);
    }

    public function show(Transaction $transaction): JsonResponse
    {
        return response()->json([
            'data' => $transaction->load('details.product:id,product_name', 'payment', 'user:id,name'),
        ]);
    }

    /** Member: own order history. */
    public function mine(Request $request): JsonResponse
    {
        $orders = Transaction::with('details.product:id,product_name', 'payment')
            ->where('user_id', $request->user()->id)
            ->latest('created_on')
            ->paginate(10);

        return response()->json($orders);
    }

    /** Guest or member: track an order using the order number (payments.midtrans_order_id). */
    public function track(string $orderNumber, ShippingService $shipping): JsonResponse
    {
        $transaction = Transaction::with('details.product:id,product_name', 'payment')
            ->whereHas('payments', fn ($q) => $q->where('midtrans_order_id', $orderNumber))
            ->firstOrFail();

        $tracking = null;

        if ($transaction->tracking_number && $transaction->courier) {
            try {
                $tracking = $shipping->trackWaybill($transaction->tracking_number, $transaction->courier);
            } catch (RuntimeException) {
                $tracking = null; // courier tracking is optional
            }
        }

        return response()->json([
            'data' => [
                'order_number' => $orderNumber,
                'status' => $transaction->status,
                'payment_status' => $transaction->payment?->payment_status,
                'total' => $transaction->total,
                'courier' => $transaction->courier,
                'tracking_number' => $transaction->tracking_number,
                'items' => $transaction->details->map(fn ($d) => [
                    'product_name' => $d->product->product_name,
                    'qty' => $d->qty,
                    'subtotal' => $d->subtotal,
                ]),
                'tracking' => $tracking,
            ],
        ]);
    }

    public function trackingNumber(Request $request, Transaction $transaction): JsonResponse
    {
        $data = $request->validate(['tracking_number' => ['required', 'string', 'max:50']]);

        try {
            $this->service->inputTrackingNumber($transaction, $data['tracking_number']);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Tracking number saved.', 'data' => $transaction->fresh()]);
    }

    public function status(Request $request, Transaction $transaction): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:' . implode(',', [
                Transaction::PROCESSING, Transaction::READY_FOR_PICKUP, Transaction::COMPLETED,
            ])],
        ]);

        try {
            $this->service->changeStatus($transaction, $data['status']);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Status updated.', 'data' => $transaction->fresh()]);
    }
}
