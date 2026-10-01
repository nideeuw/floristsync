<?php

namespace App\Services;

use App\Models\Product;
use DomainException;
use Illuminate\Support\Collection;

class CartService
{
    private const KEY = 'cart'; // session: [product_id => qty]

    /** @return array<int,int> */
    public function raw(): array
    {
        return session(self::KEY, []);
    }

    public function count(): int
    {
        return (int) array_sum($this->raw());
    }

    public function add(Product $product, int $qty = 1): void
    {
        $newQty = min(($this->raw()[$product->id] ?? 0) + $qty, $product->stock);

        if ($newQty < 1) {
            throw new DomainException("{$product->product_name} is out of stock.");
        }

        $this->put($product->id, $newQty);
    }

    public function update(Product $product, int $qty): void
    {
        $qty = min($qty, $product->stock);

        if ($qty < 1) {
            $this->remove($product);
        } else {
            $this->put($product->id, $qty);
        }
    }

    public function remove(Product $product): void
    {
        $cart = $this->raw();
        unset($cart[$product->id]);
        session([self::KEY => $cart]);
    }

    public function clear(): void
    {
        session()->forget(self::KEY);
    }

    /** Cart lines with fresh prices and stock from the database. */
    public function items(): Collection
    {
        $cart = $this->raw();

        if (! $cart) {
            return collect();
        }

        return Product::with('category')
            ->available()
            ->whereIn('id', array_keys($cart))
            ->get()
            ->map(function (Product $product) use ($cart) {
                $qty = min($cart[$product->id], $product->stock);

                return (object) [
                    'product' => $product,
                    'qty' => $qty,
                    'subtotal' => $product->price * $qty,
                ];
            })
            ->filter(fn ($line) => $line->qty > 0)
            ->values();
    }

    private function put(int $productId, int $qty): void
    {
        $cart = $this->raw();
        $cart[$productId] = $qty;
        session([self::KEY => $cart]);
    }
}
