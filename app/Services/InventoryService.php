<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Stock;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function __construct(private NotificationService $notifications) {}

    /** Current stock of one product, read from view_stock. */
    public function currentStock(int $productId): int
    {
        return (int) DB::table('view_stock')->where('product_id', $productId)->value('stock');
    }

    public function reduce(Product $product, int $qty, string $notes = 'Sale'): void
    {
        DB::transaction(function () use ($product, $qty, $notes) {
            $this->lockProduct($product->id);

            if ($this->currentStock($product->id) < $qty) {
                throw new DomainException("Insufficient stock for {$product->product_name}.");
            }

            $this->log($product->id, -$qty, 'out', $notes);
        });

        $this->notifyIfLow($product);
    }

    public function add(Product $product, int $qty, string $changeType = 'in', string $notes = 'Restock'): void
    {
        $this->log($product->id, $qty, $changeType, $notes);
    }

    public function adjust(Product $product, int $change, string $notes, ?string $reason = null): void
    {
        if ($change === 0) {
            throw new DomainException('Quantity change cannot be zero.');
        }

        if ($change > 0) {
            $this->add($product, $change, 'adjustment', $notes);

            return;
        }

        DB::transaction(function () use ($product, $change, $notes, $reason) {
            $this->lockProduct($product->id);

            if ($this->currentStock($product->id) < abs($change)) {
                throw new DomainException("Stock of {$product->product_name} cannot go below zero.");
            }

            if (empty($reason)) {
                throw new DomainException('A reason is required for negative stock adjustments.');
            }

            $this->log($product->id, $change, 'adjustment', $notes, $reason);
        });

        $this->notifyIfLow($product);
    }

    public function lowStock(): Collection
    {
        return Product::active()
            ->join('view_stock', 'view_stock.product_id', '=', 'products.id')
            ->where('view_stock.stock', '<=', config('floristsync.low_stock_threshold'))
            ->orderBy('view_stock.stock')
            ->get(['products.id', 'products.product_name', 'view_stock.stock']);
    }

    /** Row lock so two buyers cannot take the last item at the same time. */
    private function lockProduct(int $productId): void
    {
        Product::whereKey($productId)->lockForUpdate()->first();
    }

    private function notifyIfLow(Product $product): void
    {
        $current = Product::withStock()->find($product->id);

        if ($current) {
            $this->notifications->notifyLowStock($current);
        }
    }

    private function log(int $productId, int $change, string $changeType, string $notes, ?string $reason = null): void
    {
        Stock::create([
            'product_id' => $productId,
            'quantity_change' => $change,
            'change_type' => $changeType,
            'reason' => $reason,
            'notes' => $notes,
            'date' => now(),
        ]);
    }
}
