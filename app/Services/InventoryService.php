<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Stock;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class InventoryService
{
    public function reduce(Product $product, int $qty, string $notes = 'Sale'): void
    {
        $updated = Product::whereKey($product->id)
            ->where('stock', '>=', $qty)
            ->decrement('stock', $qty, $this->auditFields());

        if (! $updated) {
            throw new DomainException("Insufficient stock for {$product->product_name}.");
        }

        $this->log($product->id, -$qty, 'out', $notes);
    }

    public function add(Product $product, int $qty, string $changeType = 'in', string $notes = 'Restock'): void
    {
        Product::whereKey($product->id)->increment('stock', $qty, $this->auditFields());

        $this->log($product->id, $qty, $changeType, $notes);
    }

    public function adjust(Product $product, int $change, string $notes): void
    {
        if ($change === 0) {
            throw new DomainException('Quantity change cannot be zero.');
        }

        if ($change > 0) {
            $this->add($product, $change, 'adjustment', $notes);

            return;
        }

        $qty = abs($change);

        $updated = Product::whereKey($product->id)
            ->where('stock', '>=', $qty)
            ->decrement('stock', $qty, $this->auditFields());

        if (! $updated) {
            throw new DomainException("Stock of {$product->product_name} cannot go below zero.");
        }

        $this->log($product->id, $change, 'adjustment', $notes);
    }

    public function lowStock(): Collection
    {
        return Product::active()->lowStock()->orderBy('stock')
            ->get(['id', 'product_name', 'stock']);
    }

    private function log(int $productId, int $change, string $changeType, string $notes): void
    {
        Stock::create([
            'product_id' => $productId,
            'quantity_change' => $change,
            'change_type' => $changeType,
            'notes' => $notes,
            'date' => now(),
        ]);
    }

    private function auditFields(): array
    {
        return ['modified_by' => Auth::id(), 'modified_on' => now()];
    }
}
