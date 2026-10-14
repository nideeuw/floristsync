<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class OrderShippingService
{
    /** Delivery orders shown on the shipping page. */
    public const STATUSES = [Transaction::PROCESSING, Transaction::SHIPPED];

    /** Delivery orders only (courier is set). Pickup orders never ship. */
    private function base(): Builder
    {
        return Transaction::where('transaction_type', Transaction::TYPE_ONLINE)
            ->whereNotNull('courier')
            ->where('courier', '<>', '')
            ->whereIn('status', self::STATUSES);
    }

    /** Order count per status: [status => total]. */
    public function counts(): Collection
    {
        return $this->base()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
    }

    public function paginate(?string $status = null, int $perPage = 15): LengthAwarePaginator
    {
        return $this->base()
            ->with('payment', 'user:id,name')
            ->when(
                in_array($status, self::STATUSES, true),
                fn (Builder $q) => $q->where('status', $status)
            )
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }
}