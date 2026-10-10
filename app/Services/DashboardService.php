<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Transaction;

class DashboardService
{
    public function __construct(private InventoryService $inventory) {}

    public function orderSummary(int $recentLimit = 10): array
    {
        $online = fn () => Transaction::where('transaction_type', Transaction::TYPE_ONLINE);

        $byPaymentStatus = $online()
            ->with('payment')
            ->get()
            ->groupBy(fn (Transaction $transaction) => $transaction->payment?->payment_status ?? 'unknown')
            ->map->count();

        return [
            'total' => $online()->count(),
            'byPaymentStatus' => $byPaymentStatus,
            'recentOrders' => $online()
                ->with('payment', 'user:id,name')
                ->latest('id')
                ->take($recentLimit)
                ->get(),
        ];
    }

    public function inventorySummary(): array
    {
        return [
            'totalProducts' => Product::count(),
            'outOfStock' => Product::where('stock', 0)->count(),
            'lowStock' => $this->inventory->lowStock(),
        ];
    }
}
