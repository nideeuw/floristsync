<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TransactionHistoryService
{
    public function statuses(): array
    {
        return [
            Transaction::PENDING_PAYMENT => 'Pending payment',
            Transaction::PROCESSING => 'To process',
            Transaction::READY_FOR_PICKUP => 'Ready for pickup',
            Transaction::SHIPPED => 'Shipped',
            Transaction::COMPLETED => 'Completed',
            Transaction::CANCELLED => 'Cancelled',
        ];
    }

    public function paymentMethods(): Collection
    {
        return Payment::query()
            ->whereNotNull('payment_method')
            ->distinct()
            ->orderBy('payment_method')
            ->pluck('payment_method');
    }

    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return Transaction::query()
            ->with(['user', 'cashier', 'cancelledBy', 'payment'])
            ->when($filters['type'] ?? null, fn (Builder $q, $value) => $q->where('transaction_type', $value))
            ->when($filters['status'] ?? null, fn (Builder $q, $value) => $q->where('status', $value))
            ->when($filters['method'] ?? null, fn (Builder $q, $value) => $q->whereHas(
                'payment',
                fn (Builder $p) => $p->where('payment_method', $value)
            ))
            ->when($filters['from'] ?? null, fn (Builder $q, $value) => $q->whereDate('created_on', '>=', $value))
            ->when($filters['to'] ?? null, fn (Builder $q, $value) => $q->whereDate('created_on', '<=', $value))
            ->when($filters['q'] ?? null, fn (Builder $q, $value) => $this->search($q, trim($value)))
            ->orderByDesc('created_on')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    private function search(Builder $query, string $term): Builder
    {
        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->whereHas('payments', fn (Builder $p) => $p->where('midtrans_order_id', 'like', '%' . $term . '%'));

            if (preg_match('/^(?:POS-)?0*(\d+)$/i', $term, $match)) {
                $q->orWhere(function (Builder $pos) use ($match) {
                    $pos->where('transaction_type', Transaction::TYPE_POS)
                        ->where('id', (int) $match[1]);
                });
            }
        });
    }
}
