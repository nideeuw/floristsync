<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ThrottleLoginRequests
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, int $maxAttempts = 5, int $decaySeconds = 60): Response
    {
        if ($request->isMethod('POST') && $request->is('login', 'admin/login', 'pos/login')) {
            $key = ($request->ip() ?? '127.0.0.1').'|'.trim($request->path(), '/');

            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                $seconds = RateLimiter::availableIn($key);

                throw new ThrottleRequestsException(
                    'Too Many Attempts.',
                    null,
                    [
                        'Retry-After' => $seconds,
                        'X-RateLimit-Limit' => $maxAttempts,
                        'X-RateLimit-Remaining' => 0,
                    ]
                );
            }

            RateLimiter::hit($key, $decaySeconds);
        }

        return $next($request);
    }
}
