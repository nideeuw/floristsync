<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\User;

class NotificationService
{
    public function notifyUser(int $userId, string $title, string $message, string $type = 'info'): void
    {
        AppNotification::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'is_read' => false,
        ]);
    }

    /** Notify every active user that has one of the given roles. */
    public function notifyRoles(array $roleNames, string $title, string $message, string $type = 'info'): void
    {
        User::active()
            ->whereHas('role', fn ($q) => $q->whereIn('role_name', $roleNames))
            ->pluck('id')
            ->each(fn ($id) => $this->notifyUser($id, $title, $message, $type));
    }
}
