<?php

namespace App\Http\Middleware;

use App\Models\Role;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $isActive = (bool) ($user->fresh()?->is_active ?? $user->is_active);

            if (! $isActive) {
                $door = match (true) {
                    $request->is('admin', 'admin/*') => 'admin.login',
                    $request->is('pos', 'pos/*') => 'cashier.login',
                    $user->hasRole(...Role::ADMIN_PANEL_ROLES) => 'admin.login',
                    $user->hasRole(...Role::POS_ROLES) => 'cashier.login',
                    default => 'login',
                };

                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                $message = 'Your account has been deactivated.';

                return redirect()->route($door)->withErrors([
                    'email' => $message,
                    'account' => $message,
                ])->with('status', $message);
            }
        }

        return $next($request);
    }
}
