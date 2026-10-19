<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class CartService
{
    private const KEY = 'cart'; // session: [product_id => qty]

    private ?int $explicitUserId = null;

    public function forUser(?User $user): self
    {
        $clone = clone $this;
        $clone->explicitUserId = $user?->id;

        return $clone;
    }

    protected function userId(): ?int
    {
        return $this->explicitUserId ?? Auth::id();
    }

    /** @return array<int,int> */
    public function raw(): array
    {
        if ($userId = $this->userId()) {
            $items = CartItem::where('user_id', $userId)
                ->where('is_active', true)
                ->pluck('qty', 'product_id')
                ->all();

            $cart = [];
            foreach ($items as $productId => $qty) {
                $cart[(int) $productId] = (int) $qty;
            }

            return $cart;
        }

        $sessionCart = session(self::KEY, []);
        $cart = [];
        foreach ($sessionCart as $productId => $qty) {
            $cart[(int) $productId] = (int) $qty;
        }

        return $cart;
    }

    public function count(): int
    {
        $cart = $this->raw();

        if (! $cart) {
            return 0;
        }

        return (int) Product::available()
            ->whereIn('id', array_keys($cart))
            ->get(['id', 'stock'])
            ->sum(fn (Product $product) => min($cart[$product->id], $product->stock));
    }

    public function add(Product $product, int $qty = 1): void
    {
        if (! $product->is_active || $product->status !== Product::STATUS_AVAILABLE) {
            throw new DomainException("{$product->product_name} is currently unavailable.");
        }

        $current = $this->raw()[$product->id] ?? 0;
        $newQty = min($current + $qty, $product->stock);

        if ($newQty < 1) {
            throw new DomainException("{$product->product_name} is out of stock.");
        }

        if ($newQty <= $current) {
            throw new DomainException("You already have all available stock of {$product->product_name} in your cart.");
        }

        $this->put($product->id, $newQty);
    }

    public function update(Product $product, int $qty): void
    {
        if (! isset($this->raw()[$product->id])) {
            return;
        }

        $qty = min($qty, $product->stock);

        if ($qty < 1) {
            $this->remove($product);
        } else {
            $this->put($product->id, $qty);
        }
    }

    public function remove(Product $product): void
    {
        if ($userId = $this->userId()) {
            CartItem::where('user_id', $userId)
                ->where('product_id', $product->id)
                ->delete();
        }

        $cart = session(self::KEY, []);
        if (isset($cart[$product->id])) {
            unset($cart[$product->id]);
            session([self::KEY => $cart]);
        }
    }

    public function clear(): void
    {
        if ($userId = $this->userId()) {
            CartItem::where('user_id', $userId)->delete();
        }

        session()->forget(self::KEY);
    }

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

    /**
     * Merge the guest session cart into a member's database cart, then clear the session cart.
     */
    public function mergeSessionCart(?User $user = null): void
    {
        $user ??= Auth::user();

        if (! $user) {
            return;
        }

        $sessionCart = session(self::KEY, []);

        if (empty($sessionCart)) {
            return;
        }

        $this->mergeGuestCart($sessionCart, $user);

        session()->forget(self::KEY);
    }

    /**
     * Merge a specific guest cart array into the user's database cart.
     *
     * @param array<int, int> $guestCart
     */
    public function mergeGuestCart(array $guestCart, User $user): void
    {
        if (empty($guestCart)) {
            return;
        }

        $products = Product::whereIn('id', array_keys($guestCart))->get()->keyBy('id');

        foreach ($guestCart as $productId => $guestQty) {
            $guestQty = (int) $guestQty;
            if ($guestQty < 1) {
                continue;
            }

            $product = $products->get($productId);

            if (! $product || ! $product->is_active || $product->status !== Product::STATUS_AVAILABLE || $product->stock < 1) {
                continue;
            }

            $existing = CartItem::where('user_id', $user->id)
                ->where('product_id', $productId)
                ->first();

            $existingQty = $existing ? (int) $existing->qty : 0;
            $newQty = min($existingQty + $guestQty, $product->stock);

            if ($newQty > 0) {
                if ($existing) {
                    $existing->update([
                        'qty' => $newQty,
                        'is_active' => true,
                    ]);
                } else {
                    CartItem::create([
                        'user_id' => $user->id,
                        'product_id' => $productId,
                        'qty' => $newQty,
                        'is_active' => true,
                    ]);
                }
            }
        }
    }

    private function put(int $productId, int $qty): void
    {
        if ($userId = $this->userId()) {
            CartItem::updateOrCreate(
                ['user_id' => $userId, 'product_id' => $productId],
                ['qty' => $qty, 'is_active' => true]
            );

            return;
        }

        $cart = session(self::KEY, []);
        $cart[$productId] = $qty;
        session([self::KEY => $cart]);
    }
}
