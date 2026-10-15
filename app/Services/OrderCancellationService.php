<?php

namespace App\Services;

use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Cancellation flow for online orders.
 *
 * - pending_payment: cancel directly (cancel the Midtrans invoice, return the stock).
 * - processing: the member sends a request, an admin approves or rejects it.
 * - ready_for_pickup, shipped, completed: cannot be cancelled (the member contacts the store on WhatsApp).
 */
class OrderCancellationService
{
    public function __construct(
        private TransactionService $transactions,
        private PaymentReversalService $reversal,
        private NotificationService $notifications,
        private InventoryService $inventory,
    ) {}

    /** Called by the member. Cancels directly or creates a pending request, depending on the order status. */
    public function request(Transaction $transaction, User $member, string $reason): Transaction
    {
        $this->assertOwnedOnlineOrder($transaction, $member);

        $reason = trim($reason);

        match ($transaction->status) {
            Transaction::PENDING_PAYMENT => $this->cancelUnpaid($transaction, $member, $reason),
            Transaction::PROCESSING => $this->createRequest($transaction, $member, $reason),
            Transaction::CANCELLED => throw new DomainException('This order has already been cancelled.'),
            default => throw new DomainException('This order can no longer be cancelled online. Please contact our customer service via WhatsApp.'),
        };

        return $transaction->refresh();
    }

    /** Cancellation requests waiting for review, oldest first. */
    public function pendingRequests(int $perPage = 15): LengthAwarePaginator
    {
        return Transaction::where('transaction_type', Transaction::TYPE_ONLINE)
            ->where('cancel_request_status', Transaction::CANCEL_PENDING)
            ->with('payment', 'user:id,name', 'details.product')
            ->orderBy('cancel_requested_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Called by an admin. Refunds the payment through the Midtrans API, returns the stock once and cancels the order.
     * If the refund fails, a DomainException is thrown and nothing is changed.
     */
    public function approve(Transaction $transaction, User $admin): Transaction
    {
        DB::transaction(function () use ($transaction, $admin) {
            $locked = Transaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();

            $this->assertReviewable($locked);

            // The row stays locked during the refund, so a double click cannot refund or restock twice.
            // The refund is idempotent, so it is safe to retry after a failure.
            $this->reversal->refund($locked);

            foreach ($locked->details()->with('product')->get() as $detail) {
                $this->inventory->add($detail->product, $detail->qty, 'cancel', "Order #{$locked->id} cancelled");
            }

            $locked->update([
                'status' => Transaction::CANCELLED,
                'cancel_request_status' => Transaction::CANCEL_APPROVED,
                'cancelled_by' => $admin->id,
                'cancelled_at' => now(),
            ]);
        });

        $this->notifyMember(
            $transaction,
            'Cancellation approved',
            "Your cancellation request for order #{$transaction->id} was approved. The order has been cancelled and your payment is being refunded."
        );

        return $transaction->refresh();
    }

    /** Called by an admin. Keeps the order as it is and marks the request as rejected. */
    public function reject(Transaction $transaction, User $admin): Transaction
    {
        DB::transaction(function () use ($transaction) {
            $locked = Transaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();

            $this->assertReviewable($locked);

            $locked->update(['cancel_request_status' => Transaction::CANCEL_REJECTED]);
        });

        $this->notifyMember(
            $transaction,
            'Cancellation rejected',
            "Your cancellation request for order #{$transaction->id} was rejected. The order is still being processed. Contact our customer service via WhatsApp if you need help."
        );

        return $transaction->refresh();
    }

    private function cancelUnpaid(Transaction $transaction, User $member, string $reason): void
    {
        $this->reversal->cancelInvoice($transaction);

        DB::transaction(function () use ($transaction, $member, $reason) {
            $locked = Transaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === Transaction::PENDING_PAYMENT) {
                $this->transactions->cancel($locked);

                $locked->update([
                    'cancelled_at' => now(),
                    'cancelled_by' => $member->id,
                    'cancel_reason' => $reason,
                ]);

                return;
            }

            if ($locked->status !== Transaction::CANCELLED) {
                throw new DomainException('The order status has just changed. Please refresh the page and try again.');
            }
        });
    }

    private function createRequest(Transaction $transaction, User $member, string $reason): void
    {
        DB::transaction(function () use ($transaction, $reason) {
            $locked = Transaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== Transaction::PROCESSING) {
                throw new DomainException('The order status has just changed. Please refresh the page and try again.');
            }

            if ($locked->cancel_request_status === Transaction::CANCEL_PENDING) {
                throw new DomainException('A cancellation request for this order is already waiting for review.');
            }

            if ($locked->cancel_request_status !== null) {
                throw new DomainException('The cancellation request for this order has already been reviewed. Please contact our customer service via WhatsApp.');
            }

            $locked->update([
                'cancel_request_status' => Transaction::CANCEL_PENDING,
                'cancel_requested_at' => now(),
                'cancel_reason' => $reason,
            ]);

            $this->notifications->notifyRoles(
                [Role::ADMIN_ORDER],
                'Cancellation request',
                "Order #{$locked->id} has a cancellation request. Please review it.",
                'order_status'
            );
        });
    }

    /** Only an online order that is still processing with a pending request can be reviewed. */
    private function assertReviewable(Transaction $transaction): void
    {
        if ($transaction->transaction_type !== Transaction::TYPE_ONLINE) {
            throw new DomainException('Only online orders have cancellation requests.');
        }

        if ($transaction->cancel_request_status !== Transaction::CANCEL_PENDING) {
            throw new DomainException('This cancellation request has already been reviewed or does not exist.');
        }

        if ($transaction->status !== Transaction::PROCESSING) {
            throw new DomainException('The order status has changed, so this request can no longer be reviewed.');
        }
    }

    private function notifyMember(Transaction $transaction, string $title, string $message): void
    {
        if ($transaction->user_id) {
            $this->notifications->notifyUser($transaction->user_id, $title, $message, 'order_status');
        }
    }

    private function assertOwnedOnlineOrder(Transaction $transaction, User $member): void
    {
        if ($transaction->transaction_type !== Transaction::TYPE_ONLINE
            || (int) $transaction->user_id !== (int) $member->id) {
            throw new AuthorizationException('This order does not belong to you.');
        }
    }
}