<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ThrottleLoginRequests
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, int $maxAttempts = 30, int $decaySeconds = 60): Response
    {
        $isLoginPost = $request->isMethod('POST') && $request->is('login', 'admin/login', 'pos/login');

        if ($isLoginPost) {
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
        }

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            if ($isLoginPost && ! Auth::check()) {
                RateLimiter::hit($key, $decaySeconds);
            }

            throw $e;
        }

        if ($isLoginPost) {
            if (Auth::check()) {
                RateLimiter::clear($key);
            } else {
                RateLimiter::hit($key, $decaySeconds);
            }
        }

        return $response;
    }
}
