<?php

namespace App\Services;

use App\Models\Shift;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;

class PosCancellationService
{
    public function __construct(
        private InventoryService $inventory,
    ) {}

    public function cancel(Transaction $transaction, User $cashier, string $reason, ?string $refundMethod = null): Transaction
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new DomainException('Cancellation reason is required.');
        }

        if ($refundMethod === null || trim($refundMethod) === '') {
            throw new DomainException('Refund method is required.');
        }

        $refundMethod = trim($refundMethod);

        if (! in_array($refundMethod, ['cash', 'debit', 'qris_manual'], true)) {
            throw new DomainException('Invalid refund method.');
        }

        DB::transaction(function () use ($transaction, $cashier, $reason, $refundMethod) {
            $locked = Transaction::whereKey($transaction->id)
                ->with(['payment', 'shift'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->transaction_type !== Transaction::TYPE_POS) {
                throw new DomainException('Only POS transactions can be cancelled.');
            }

            if ((int) $locked->cashier_id !== (int) $cashier->id) {
                throw new DomainException('You can only cancel your own transactions.');
            }

            if ($locked->status !== Transaction::COMPLETED) {
                if ($locked->status === Transaction::CANCELLED) {
                    throw new DomainException('This transaction has already been cancelled.');
                }

                throw new DomainException('Only completed POS transactions can be cancelled.');
            }

            $windowMinutes = (int) config('floristsync.pos_cancel_window_minutes', 30);
            $createdOn = $locked->created_on ?? $locked->created_at ?? now();
            if ($createdOn instanceof \DateTimeInterface) {
                $createdOn = Carbon::instance($createdOn);
            } else {
                $createdOn = Carbon::parse($createdOn);
            }

            if ($createdOn->copy()->addMinutes($windowMinutes)->lt(now())) {
                throw new DomainException('Please ask the owner.');
            }

            if ($locked->shift_id) {
                $shift = $locked->shift ?? Shift::find($locked->shift_id);
                if (! $shift || $shift->closed_at !== null) {
                    throw new DomainException('Please ask the owner.');
                }
            }

            if ($locked->payment?->payment_method === 'qris' && $refundMethod !== 'qris_manual') {
                throw new DomainException('QRIS transactions must be refunded with qris_manual.');
            }

            foreach ($locked->details()->with('product')->get() as $detail) {
                if ($detail->product) {
                    $this->inventory->add(
                        $detail->product,
                        $detail->qty,
                        'cancel',
                        "POS transaction #{$locked->id} cancelled"
                    );
                }
            }

            $locked->update([
                'status' => Transaction::CANCELLED,
                'cancel_reason' => $reason,
                'refund_method' => $refundMethod,
                'cancelled_by' => $cashier->id,
                'cancelled_at' => now(),
            ]);
        });

        app(AuditService::class)->log('pos.cancelled', 'transaction', $transaction->id, null, ['reason' => $reason, 'refund_method' => $refundMethod], userId: $cashier->id);

        return $transaction->refresh();
    }
}
