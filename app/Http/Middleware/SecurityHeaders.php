<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Send the CSP as Report-Only first. Flip to false once the browser
     * console shows no violations on checkout, payment, POS and admin pages.
     */
    private const CSP_REPORT_ONLY = true;

    private const HSTS = 'max-age=31536000';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            // geolocation stays on for "Use my location" in store settings.
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(self)',
            $this->cspHeaderName() => $this->contentSecurityPolicy(),
        ];

        if (app()->isProduction() && $request->isSecure()) {
            $headers['Strict-Transport-Security'] = self::HSTS;
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }

    private function cspHeaderName(): string
    {
        return self::CSP_REPORT_ONLY
            ? 'Content-Security-Policy-Report-Only'
            : 'Content-Security-Policy';
    }

    private function contentSecurityPolicy(): string
    {
        // Midtrans Snap and card scripts: sandbox and production.
        $midtrans = [
            'https://app.midtrans.com',
            'https://app.sandbox.midtrans.com',
            'https://api.midtrans.com',
            'https://api.sandbox.midtrans.com',
        ];

        // Vite dev server (local only).
        $viteHttp = [];
        $viteWs = [];
        if (app()->environment('local')) {
            $viteHttp = ['http://localhost:5173', 'http://127.0.0.1:5173', 'http://[::1]:5173'];
            $viteWs = ['ws://localhost:5173', 'ws://127.0.0.1:5173', 'ws://[::1]:5173'];
        }

        $directives = [
            'default-src' => ["'self'"],
            // payment.blade.php has inline scripts and Alpine needs eval, so both stay allowed for now.
            'script-src' => ["'self'", "'unsafe-inline'", "'unsafe-eval'", ...$midtrans, 'https://pay.google.com', ...$viteHttp],
            'style-src' => ["'self'", "'unsafe-inline'", 'https://fonts.googleapis.com', ...$midtrans, ...$viteHttp],
            'font-src' => ["'self'", 'data:', 'https://fonts.gstatic.com', ...$viteHttp],
            // OpenStreetMap tiles, Midtrans QR images, inline SVG/data images.
            'img-src' => ["'self'", 'data:', 'blob:', 'https://tile.openstreetmap.org', ...$midtrans],
            'connect-src' => ["'self'", ...$midtrans, ...$viteHttp, ...$viteWs],
            // 3-D Secure bank pages and the Google Maps embed come from many HTTPS hosts.
            'frame-src' => ["'self'", 'https:'],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'frame-ancestors' => ["'self'"],
        ];

        return collect($directives)
            ->map(fn (array $sources, string $name) => $name.' '.implode(' ', $sources))
            ->implode('; ');
    }
}
