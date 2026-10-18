<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\MenuPermissionMiddleware;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\ThrottleLoginRequests;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'menu.perm' => MenuPermissionMiddleware::class,
            'active' => EnsureUserIsActive::class,
            'is_active' => EnsureUserIsActive::class,
            'throttle.login' => ThrottleLoginRequests::class,
        ]);

        $middleware->web(append: [
            ThrottleLoginRequests::class,
            EnsureUserIsActive::class,
        ]);

        $middleware->redirectGuestsTo(fn (Request $request) => match (true) {
            $request->is('admin', 'admin/*') => route('admin.login'),
            $request->is('pos', 'pos/*') => route('cashier.login'),
            default => route('login'),
        });
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('orders:expire-pending')->everyFiveMinutes();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (RuntimeException $e, Request $request) {
            if ($request->is('api/shipping', 'api/shipping/*')) {
                return response()->json([
                    'message' => $e->getMessage() ?: 'Shipping service unavailable.',
                ], 502);
            }
        });
    })->create();
