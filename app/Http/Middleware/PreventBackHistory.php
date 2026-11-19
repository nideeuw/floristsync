<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class PreventBackHistory
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldApply($request, $response)) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }

    /**
     * Determine whether the no-cache headers should be applied to the response.
     */
    protected function shouldApply(Request $request, Response $response): bool
    {
        // Do NOT apply to binary file downloads or attachments
        if ($response instanceof BinaryFileResponse) {
            return false;
        }

        $contentDisposition = (string) $response->headers->get('Content-Disposition');
        if (str_contains(strtolower($contentDisposition), 'attachment')) {
            return false;
        }

        // Do NOT apply to file/print responses: report PDF and Excel, printable invoice
        if ($request->is(
            'admin/reports/export/*',
            'track/*/invoice',
            '*/invoice',
        )) {
            return false;
        }

        // Do NOT apply to static assets, webhooks, or API routes
        if ($request->is(
            'build/*',
            'images/*',
            'api/payment/webhook',
            'payment/webhook',
            'api/*',
            '*webhook*',
        )) {
            return false;
        }

        // Apply on the three login pages
        if ($request->is('login', 'admin/login', 'pos/login')) {
            return true;
        }

        // Apply on every response for an authenticated user
        if (Auth::check() || $request->user() !== null) {
            return true;
        }

        // Do NOT apply to guest pages (home, catalog, product detail, etc.)
        return false;
    }
}

