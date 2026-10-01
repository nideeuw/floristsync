<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Product;
use App\Models\Role;
use App\Models\Transaction;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class TransactionService
{
    public function __construct(
        private InventoryService $inventory,
        private MidtransService $midtrans,
        private ShippingService $shipping,
        private NotificationService $notifications,
    ) {}

    /**
     * POS (cashier) transaction.
     * $items = [['product_id' => 1, 'qty' => 2], ...]
     */
    public function createPosTransaction(array $items, int $cashierId, string $paymentMethod, int $paidAmount = 0): Transaction
    {
        return DB::transaction(function () use ($items, $cashierId, $paymentMethod, $paidAmount) {
            [$rows, $subtotal] = $this->prepareItems($items);

            if ($paymentMethod === 'cash' && $paidAmount < $subtotal) {
                throw new DomainException('Cash payment is not enough.');
            }

            $transaction = Transaction::create([
                'cashier_id' => $cashierId,
                'transaction_type' => Transaction::TYPE_POS,
                'status' => Transaction::COMPLETED,
                'total' => $subtotal,
            ]);

            $this->saveDetailsAndReduceStock($transaction, $rows);

            Payment::create([
                'transaction_id' => $transaction->id,
                'payment_method' => $paymentMethod,
                'payment_status' => 'settlement',
                'amount' => $subtotal,
                'payment_date' => now(),
            ]);

            return $transaction->load('details.product', 'payment');
        });
    }

    /**
     * Online order (guest or member).
     * $customer = ['name', 'email', 'phone']
     * $delivery = ['method' => 'pickup|delivery', 'address', 'destination_id', 'courier', 'service']
     */
    public function createOnlineTransaction(array $items, array $customer, array $delivery, ?int $userId = null): Transaction
    {
        $isDelivery = $delivery['method'] === 'delivery';

        // Ask RajaOngkir first (outside the DB transaction). Never trust a cost sent by the browser.
        $shippingCost = $isDelivery
            ? $this->shipping->getServiceCost(
                (int) $delivery['destination_id'],
                $this->shipping->calculateWeight($items),
                $delivery['courier'],
                $delivery['service'],
            )
            : 0;

        $transaction = DB::transaction(function () use ($items, $customer, $delivery, $userId, $isDelivery, $shippingCost) {
            [$rows, $subtotal] = $this->prepareItems($items);

            $transaction = Transaction::create([
                'user_id' => $userId, // null = guest
                'transaction_type' => Transaction::TYPE_ONLINE,
                'status' => Transaction::PENDING_PAYMENT,
                'shipping_address' => $isDelivery
                    ? "{$customer['name']} ({$customer['phone']})\n{$delivery['address']}"
                    : null,
                'courier' => $isDelivery ? $delivery['courier'] : null,
                'total' => $subtotal + $shippingCost,
            ]);

            $this->saveDetailsAndReduceStock($transaction, $rows);

            Payment::create([
                'transaction_id' => $transaction->id,
                'midtrans_order_id' => 'FS-' . $transaction->id . '-' . strtoupper(Str::random(6)),
                'payment_status' => 'pending',
                'amount' => $transaction->total,
            ]);

            return $transaction->load('details.product', 'payment');
        });

        // Call Midtrans outside the DB transaction.
        try {
            $token = $this->midtrans->createSnapToken($transaction, $customer);
            $transaction->payment->update(['snap_token' => $token]);
        } catch (Throwable $e) {
            $this->cancel($transaction);
            throw $e;
        }

        return $transaction;
    }

    /** Called from the Midtrans webhook. Safe to run more than once (idempotent). */
    public function updatePaymentStatus(array $notification): void
    {
        DB::transaction(function () use ($notification) {
            $payment = Payment::where('midtrans_order_id', $notification['order_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $transaction = $payment->transaction;
            $status = $notification['transaction_status'];

            $payment->update([
                'payment_status' => $status,
                'payment_method' => $notification['payment_type'],
            ]);

            $paid = $status === 'settlement'
                || ($status === 'capture' && $notification['fraud_status'] === 'accept');

            if ($paid && $transaction->status === Transaction::PENDING_PAYMENT) {
                $payment->update(['payment_date' => now()]);
                $transaction->update(['status' => Transaction::PROCESSING]);

                $this->notifications->notifyRoles(
                    [Role::ADMIN, Role::CASHIER],
                    'New paid order',
                    "Order #{$transaction->id} has been paid and is ready to be processed.",
                    'new_order'
                );

                if ($transaction->user_id) {
                    $this->notifications->notifyUser(
                        $transaction->user_id,
                        'Payment received',
                        "Your order #{$transaction->id} is being processed.",
                        'order_status'
                    );
                }
            } elseif (in_array($status, ['deny', 'cancel', 'expire', 'failure'], true)) {
                $this->cancel($transaction);
            }
        });
    }

    /** Cancel an unpaid order and return the stock. */
    public function cancel(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $transaction->refresh();

            if ($transaction->status !== Transaction::PENDING_PAYMENT) {
                return; // already processed or cancelled, do not restock twice
            }

            foreach ($transaction->details()->with('product')->get() as $detail) {
                $this->inventory->add($detail->product, $detail->qty, 'cancel', "Order #{$transaction->id} cancelled");
            }

            $transaction->update(['status' => Transaction::CANCELLED]);
        });
    }

    /** Admin enters the courier tracking number. Status becomes "shipped". */
    public function inputTrackingNumber(Transaction $transaction, string $trackingNumber): Transaction
    {
        if (! $transaction->isDelivery() || $transaction->status !== Transaction::PROCESSING) {
            throw new DomainException('Tracking number can only be set for delivery orders that are being processed.');
        }

        $transaction->update(['tracking_number' => $trackingNumber, 'status' => Transaction::SHIPPED]);

        $this->notifyStatus($transaction, "Your order #{$transaction->id} has been shipped. Tracking number: {$trackingNumber}.");

        return $transaction;
    }

    public function changeStatus(Transaction $transaction, string $newStatus): Transaction
    {
        if ($transaction->transaction_type !== Transaction::TYPE_ONLINE || $transaction->status === Transaction::PENDING_PAYMENT) {
            throw new DomainException('This order status cannot be changed.');
        }

        $transaction->update(['status' => $newStatus]);

        $label = str_replace('_', ' ', $newStatus);
        $this->notifyStatus($transaction, "Your order #{$transaction->id} is now: {$label}.");

        return $transaction;
    }

    // ---------- private ----------

    private function notifyStatus(Transaction $transaction, string $message): void
    {
        if ($transaction->user_id) {
            $this->notifications->notifyUser($transaction->user_id, 'Order update', $message, 'order_status');
        }
    }

    /** Get prices from the DB and lock product rows. Must run inside DB::transaction. */
    private function prepareItems(array $items): array
    {
        $products = Product::available()
            ->whereIn('id', collect($items)->pluck('product_id'))
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $rows = [];
        $subtotal = 0;

        foreach ($items as $item) {
            $product = $products->get($item['product_id']);

            if (! $product) {
                throw new DomainException('Some products were not found or are not available.');
            }

            $qty = (int) $item['qty'];
            $lineTotal = $product->price * $qty;
            $subtotal += $lineTotal;

            $rows[] = ['product' => $product, 'qty' => $qty, 'unit_price' => $product->price, 'subtotal' => $lineTotal];
        }

        return [$rows, $subtotal];
    }

    private function saveDetailsAndReduceStock(Transaction $transaction, array $rows): void
    {
        foreach ($rows as $row) {
            $transaction->details()->create([
                'product_id' => $row['product']->id,
                'qty' => $row['qty'],
                'unit_price' => $row['unit_price'],
                'subtotal' => $row['subtotal'],
            ]);

            $this->inventory->reduce($row['product'], $row['qty'], "Sale - order #{$transaction->id}");
        }
    }
}
