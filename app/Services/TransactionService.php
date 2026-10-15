<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Product;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class TransactionService
{
    private const FAILED_STATUSES = ['deny', 'cancel', 'expire', 'failure'];

    private const AFTER_PAYMENT_STATUSES = ['refund', 'partial_refund', 'chargeback', 'partial_chargeback'];

    public function __construct(
        private InventoryService $inventory,
        private MidtransService $midtrans,
        private ShippingService $shipping,
        private NotificationService $notifications,
        private ShiftService $shifts,
    ) {}

    public function createPosTransaction(
        array $items,
        int $cashierId,
        string $paymentMethod,
        int $paidAmount = 0,
        ?int $userId = null,
        ?string $notes = null
    ): Transaction {
        return DB::transaction(function () use ($items, $cashierId, $paymentMethod, $paidAmount, $userId, $notes) {
            [$rows, $subtotal] = $this->prepareItems($items);

            if ($paymentMethod === 'cash' && $paidAmount < $subtotal) {
                throw new DomainException('Cash payment is not enough.');
            }

            $cashier = User::find($cashierId);
            $shift = $cashier ? $this->shifts->current($cashier) : null;

            $isQris = $paymentMethod === 'qris';

            $transaction = Transaction::create([
                'user_id' => $userId,
                'cashier_id' => $cashierId,
                'shift_id' => $shift?->id,
                'transaction_type' => Transaction::TYPE_POS,
                'status' => $isQris ? Transaction::PENDING_PAYMENT : Transaction::COMPLETED,
                'notes' => $notes,
                'total' => $subtotal,
            ]);

            $this->saveDetailsAndReduceStock($transaction, $rows);

            Payment::create([
                'transaction_id' => $transaction->id,
                'midtrans_order_id' => $isQris ? 'FS-POS-'.$transaction->id.'-'.strtoupper(Str::random(6)) : null,
                'payment_method' => $paymentMethod,
                'payment_status' => $isQris ? 'pending' : 'settlement',
                'amount' => $subtotal,
                'payment_date' => $isQris ? null : now(),
            ]);

            return $transaction->load('details.product', 'payment', 'user');
        });
    }

    public function createOnlineTransaction(array $items, array $customer, array $delivery, int $userId): Transaction
    {
        $isDelivery = $delivery['method'] === 'delivery';

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
                'user_id' => $userId,
                'transaction_type' => Transaction::TYPE_ONLINE,
                'status' => Transaction::PENDING_PAYMENT,
                'shipping_address' => $isDelivery
                    ? "{$customer['name']} ({$customer['phone']})\n{$delivery['address']}"
                    : null,
                'courier' => $isDelivery ? $delivery['courier'] : null,
                'notes' => $customer['notes'] ?? null,
                'total' => $subtotal + $shippingCost,
            ]);

            $this->saveDetailsAndReduceStock($transaction, $rows);

            Payment::create([
                'transaction_id' => $transaction->id,
                'midtrans_order_id' => 'FS-'.$transaction->id.'-'.strtoupper(Str::random(6)),
                'payment_status' => 'pending',
                'amount' => $transaction->total,
            ]);

            return $transaction->load('details.product', 'payment');
        });

        try {
            $token = $this->midtrans->createSnapToken($transaction, $customer);
            $transaction->payment->update(['snap_token' => $token]);
        } catch (Throwable $e) {
            $this->cancel($transaction);
            Log::error('Snap token failed for order #'.$transaction->id.': '.$e->getMessage());

            throw new \RuntimeException('Payment could not be started right now. Please try again.', 0, $e);
        }

        return $transaction;
    }

    public function updatePaymentStatus(array $notification): void
    {
        DB::transaction(function () use ($notification) {
            $payment = Payment::where('midtrans_order_id', $notification['order_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertAmountMatches($payment, $notification);

            $transaction = Transaction::whereKey($payment->transaction_id)
                ->lockForUpdate()
                ->firstOrFail();

            $status = $notification['transaction_status'];

            if ($payment->payment_date !== null && ! in_array($status, self::AFTER_PAYMENT_STATUSES, true) && ! ($status === 'settlement' && $payment->payment_status === 'capture')) {
                return;
            }

            $payment->update([
                'payment_status' => $status,
                'payment_method' => $notification['payment_type'] ?? $payment->payment_method,
            ]);

            if ($this->isPaid($notification)) {
                $this->markPaid($payment, $transaction);
            } elseif (in_array($status, self::FAILED_STATUSES, true)) {
                $this->cancel($transaction);
            }
        });
    }

    public function cancel(Transaction $transaction): void
    {
        $remoteOrderId = DB::transaction(function () use ($transaction) {
            $locked = Transaction::whereKey($transaction->id)->lockForUpdate()->first();

            if (! $locked || $locked->status !== Transaction::PENDING_PAYMENT) {
                return null;
            }

            foreach ($locked->details()->with('product')->get() as $detail) {
                $this->inventory->add($detail->product, $detail->qty, 'cancel', "Order #{$locked->id} cancelled");
            }

            $locked->update(['status' => Transaction::CANCELLED]);

            $payment = $locked->payment;
            $remoteOrderId = null;

            if ($payment && $payment->payment_status === 'pending') {
                if ($payment->snap_token || $payment->midtrans_order_id) {
                    $remoteOrderId = $payment->midtrans_order_id;
                }

                $payment->update(['payment_status' => 'cancel']);
            }

            return $remoteOrderId;
        });

        $transaction->refresh();

        if ($remoteOrderId) {
            $this->midtrans->cancelInvoice($remoteOrderId);
        }
    }

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
        if ($transaction->transaction_type !== Transaction::TYPE_ONLINE || in_array($transaction->status, [Transaction::PENDING_PAYMENT, Transaction::CANCELLED], true)) {
            throw new DomainException('This order status cannot be changed.');
        }

        $transaction->update(['status' => $newStatus]);

        $label = str_replace('_', ' ', $newStatus);
        $this->notifyStatus($transaction, "Your order #{$transaction->id} is now: {$label}.");

        return $transaction;
    }

    private function isPaid(array $notification): bool
    {
        $status = $notification['transaction_status'];

        return $status === 'settlement'
            || ($status === 'capture' && ($notification['fraud_status'] ?? null) === 'accept');
    }

    private function markPaid(Payment $payment, Transaction $transaction): void
    {
        if ($transaction->status === Transaction::CANCELLED) {
            Log::warning("Payment {$payment->midtrans_order_id} was paid after order #{$transaction->id} was cancelled. Manual refund needed.");

            return;
        }

        if ($transaction->status !== Transaction::PENDING_PAYMENT) {
            return;
        }

        $payment->update(['payment_date' => now()]);

        if ($transaction->transaction_type === Transaction::TYPE_POS) {
            $transaction->update(['status' => Transaction::COMPLETED]);

            return;
        }

        $transaction->update(['status' => Transaction::PROCESSING]);

        $this->notifications->notifyRoles(
            [Role::ADMIN_ORDER],
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
    }

    private function assertAmountMatches(Payment $payment, array $notification): void
    {
        if (! isset($notification['gross_amount'])) {
            return;
        }

        if ((int) round((float) $notification['gross_amount']) !== (int) round((float) $payment->amount)) {
            throw new DomainException("Amount mismatch for order {$payment->midtrans_order_id}.");
        }
    }

    private function notifyStatus(Transaction $transaction, string $message): void
    {
        if ($transaction->user_id) {
            $this->notifications->notifyUser($transaction->user_id, 'Order update', $message, 'order_status');
        }
    }

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
