<?php

namespace App\Services;

use App\Models\Transaction;

class OrderMilestoneService
{
    /** Board columns in lifecycle order: status => label. */
    public const COLUMNS = [
        Transaction::PENDING_PAYMENT  => 'Pending payment',
        Transaction::PROCESSING       => 'To process',
        Transaction::READY_FOR_PICKUP => 'Ready for pickup',
        Transaction::SHIPPED          => 'Shipped',
        Transaction::COMPLETED        => 'Completed',
        Transaction::CANCELLED        => 'Cancelled',
    ];

    public const LATEST_PER_COLUMN = 5;

    /**
     * Online orders only (POS sales never enter the milestone flow).
     * Returns one entry per column: status, label, total count and the latest orders.
     */
    public function board(): array
    {
        $counts = Transaction::where('transaction_type', Transaction::TYPE_ONLINE)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $columns = [];

        foreach (self::COLUMNS as $status => $label) {
            $columns[] = [
                'status' => $status,
                'label'  => $label,
                'count'  => (int) $counts->get($status, 0),
                'orders' => Transaction::where('transaction_type', Transaction::TYPE_ONLINE)
                    ->where('status', $status)
                    ->with('payment', 'user:id,name')
                    ->latest('id')
                    ->limit(self::LATEST_PER_COLUMN)
                    ->get(),
            ];
        }

        return $columns;
    }
}