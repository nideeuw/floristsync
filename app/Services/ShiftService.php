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

    public function open(User $cashier): Shift
    {
        $existing = $this->current($cashier);
        if ($existing !== null) {
            return $existing;
        }

        return Shift::create([
            'cashier_id' => $cashier->id,
            'opened_at' => now(),
            'created_by' => $cashier->id,
            'modified_by' => $cashier->id,
        ]);
    }

    public function close(Shift $shift): Shift
    {
        if ($shift->closed_at === null) {
            $shift->update([
                'closed_at' => now(),
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
            ->with('payment')
            ->withCount('details')
            ->latest('id')
            ->paginate($perPage);

        return [
            'summary' => $summary,
            'byMethod' => $byMethod,
            'transactions' => $transactions,
        ];
    }
}
