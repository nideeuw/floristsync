<?php

namespace App\Services;

use App\Models\Shift;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ShiftService
{
    public function current(User $cashier): ?Shift
    {
        return Shift::active()
            ->where('cashier_id', $cashier->id)
            ->whereNull('closed_at')
            ->latest('id')
            ->first();
    }

    public function open(User $cashier, ?int $openingCash = 0): Shift
    {
        $existing = $this->current($cashier);
        if ($existing !== null) {
            return $existing;
        }

        return Shift::create([
            'cashier_id' => $cashier->id,
            'opened_at' => now(),
            'opening_cash' => $openingCash ?? 0,
            'created_by' => $cashier->id,
            'modified_by' => $cashier->id,
        ]);
    }

    public function setOpeningCash(Shift $shift, int $amount): Shift
    {
        $shift->update([
            'opening_cash' => max(0, $amount),
            'modified_by' => Auth::id() ?? $shift->cashier_id,
        ]);

        return $shift;
    }

    public function getCashReconciliation(Shift $shift): array
    {
        $opening = (int) ($shift->opening_cash ?? 0);

        $cashSales = (int) DB::table('transactions as t')
            ->join('payments as p', 'p.transaction_id', '=', 't.id')
            ->where('t.shift_id', $shift->id)
            ->where('t.transaction_type', Transaction::TYPE_POS)
            ->where('p.payment_method', 'cash')
            ->whereIn('t.status', [Transaction::COMPLETED, Transaction::CANCELLED])
            ->sum('t.total');

        $cancelledCashSales = (int) DB::table('transactions as t')
            ->join('payments as p', 'p.transaction_id', '=', 't.id')
            ->where('t.shift_id', $shift->id)
            ->where('t.transaction_type', Transaction::TYPE_POS)
            ->where('t.status', Transaction::CANCELLED)
            ->where(function ($q) {
                $q->where('t.refund_method', 'cash')
                    ->orWhere(function ($q2) {
                        $q2->whereNull('t.refund_method')
                            ->where('p.payment_method', 'cash');
                    });
            })
            ->sum('t.total');

        $expected = $opening + $cashSales - $cancelledCashSales;

        return [
            'opening_cash' => $opening,
            'cash_sales' => $cashSales,
            'cancelled_cash_sales' => $cancelledCashSales,
            'expected_cash' => $expected,
        ];
    }

    public function close(Shift $shift, ?int $countedCash = null): Shift
    {
        if ($shift->closed_at === null) {
            $difference = null;
            if ($countedCash !== null) {
                $reconciliation = $this->getCashReconciliation($shift);
                $difference = $countedCash - $reconciliation['expected_cash'];
            }

            $shift->update([
                'closed_at' => now(),
                'closing_cash_counted' => $countedCash,
                'cash_difference' => $difference,
                'modified_by' => Auth::id() ?? $shift->cashier_id,
            ]);
        }

        return $shift;
    }

    public function getShiftSummary(?Shift $shift, User $cashier, int $perPage = 15): array
    {
        $query = Transaction::where('transaction_type', Transaction::TYPE_POS)
            ->where('cashier_id', $cashier->id);

        if ($shift) {
            $query->where('shift_id', $shift->id);
        } else {
            $query->where('created_on', '>=', now()->startOfDay());
        }

        $summary = (clone $query)
            ->successful()
            ->selectRaw('COUNT(*) as orders, COALESCE(SUM(total), 0) as revenue')
            ->first();

        $byMethodQuery = DB::table('payments as p')
            ->join('transactions as t', 't.id', '=', 'p.transaction_id')
            ->where('t.transaction_type', Transaction::TYPE_POS)
            ->where('t.cashier_id', $cashier->id)
            ->whereNotIn('t.status', [Transaction::PENDING_PAYMENT, Transaction::CANCELLED]);

        if ($shift) {
            $byMethodQuery->where('t.shift_id', $shift->id);
        } else {
            $byMethodQuery->where('t.created_on', '>=', now()->startOfDay());
        }

        $byMethod = $byMethodQuery
            ->selectRaw('p.payment_method, COUNT(*) as orders, SUM(p.amount) as total')
            ->groupBy('p.payment_method')
            ->get()
            ->keyBy('payment_method');

        $transactions = (clone $query)
            ->with(['payment', 'user'])
            ->withCount('details')
            ->latest('id')
            ->paginate($perPage);

        return [
            'summary' => $summary,
            'byMethod' => $byMethod,
            'transactions' => $transactions,
            'reconciliation' => $shift ? $this->getCashReconciliation($shift) : null,
        ];
    }
}
