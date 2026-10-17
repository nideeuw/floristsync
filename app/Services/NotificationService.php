<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Str;

class NotificationService
{
    public function notifyUser(int $userId, string $title, string $message, string $type = 'info', ?string $link = null): void
    {
        AppNotification::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'link' => $link,
            'is_read' => false,
        ]);
    }

    public function notifyRoles(array $roleNames, string $title, string $message, string $type = 'info', ?string $link = null): void
    {
        User::active()
            ->whereHas('role', fn ($q) => $q->whereIn('role_name', $roleNames))
            ->pluck('id')
            ->each(fn ($id) => $this->notifyUser($id, $title, $message, $type, $link));
    }

    public function notifyLowStock(Product $product): void
    {
        if ($product->stock > config('floristsync.low_stock_threshold')) {
            return;
        }

        $title = Str::limit("Low stock: {$product->product_name}", 150, '');
        $message = "{$product->product_name} has {$product->stock} left. Please restock soon.";
        $link = route('admin.stock.index', [], false);

        User::active()
            ->whereHas('role', fn ($q) => $q->where('role_name', Role::ADMIN_INVENTORY))
            ->pluck('id')
            ->each(function ($userId) use ($title, $message, $link) {
                $alreadyUnread = AppNotification::where('user_id', $userId)
                    ->where('type', 'low_stock')
                    ->where('title', $title)
                    ->where('is_read', false)
                    ->exists();

                if (! $alreadyUnread) {
                    $this->notifyUser($userId, $title, $message, 'low_stock', $link);
                }
            });
    }
}
