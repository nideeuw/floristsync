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
        '' => 'public-layout',
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
        $item = $request->user()->appNotifications()
            ->whereKey($notification)
            ->firstOrFail();

        $item->update([
            'is_read' => true,
            'modified_by' => $request->user()->id,
            'modified_on' => now(),
        ]);

        return $this->safeRedirect($request, $item->link);
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

    private function safeRedirect(Request $request, ?string $link): RedirectResponse
    {
        if (blank($link)) {
            return back();
        }

        $link = trim((string) $link);

        // Internal path starting with single slash (disallowing protocol-relative // or /\)
        if (str_starts_with($link, '/') && ! str_starts_with($link, '//') && ! str_starts_with($link, '/\\')) {
            return redirect()->to($link);
        }

        // Full URL: must be valid URL and match request host or configured APP_URL host
        if (filter_var($link, FILTER_VALIDATE_URL)) {
            $parsed = parse_url($link);
            $scheme = strtolower($parsed['scheme'] ?? '');
            $host = strtolower($parsed['host'] ?? '');

            $allowedHosts = array_filter([
                strtolower($request->getHost()),
                strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST)),
            ]);

            if (in_array($scheme, ['http', 'https'], true) && in_array($host, $allowedHosts, true)) {
                return redirect()->to($link);
            }
        }

        return back();
    }
}
