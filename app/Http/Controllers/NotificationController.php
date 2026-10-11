<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class NotificationController extends Controller
{
    private const LAYOUTS = [
        'admin.' => 'staff-layout',
        ''       => 'public-layout',
    ];

    public function index(Request $request): View
    {
        $prefix = $this->prefix($request);
        $query = $request->user()->appNotifications();

        return view('notifications.index', [
            'notifications' => (clone $query)->latest('created_on')->latest('id')->paginate(15),
            'unread' => (clone $query)->where('is_read', false)->count(),
            'prefix' => $prefix,
            'layout' => self::LAYOUTS[$prefix],
        ]);
    }

    public function markRead(Request $request, int $notification): RedirectResponse
    {
        $request->user()->appNotifications()
            ->whereKey($notification)
            ->firstOrFail()
            ->update(['is_read' => true]);

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->appNotifications()
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'modified_by' => $request->user()->id,
                'modified_on' => now(),
            ]);

        return back();
    }

    private function prefix(Request $request): string
    {
        return Str::before((string) $request->route()?->getName(), 'notifications');
    }
}
