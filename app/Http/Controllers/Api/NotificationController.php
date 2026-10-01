<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AppNotification::where('user_id', $request->user()->id);

        return response()->json([
            'unread_count' => (clone $query)->where('is_read', false)->count(),
            'notifications' => $query->latest('created_on')->paginate(15),
        ]);
    }

    public function markRead(Request $request, AppNotification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        $notification->update(['is_read' => true]);

        return response()->json(['message' => 'Marked as read.']);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        AppNotification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true, 'modified_by' => $request->user()->id, 'modified_on' => now()]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }
}
