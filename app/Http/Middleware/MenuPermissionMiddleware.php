<?php

namespace App\Http\Middleware;

use App\Services\MenuPermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MenuPermissionMiddleware
{
    public function __construct(private MenuPermissionService $menuPermissions) {}

    public function handle(Request $request, Closure $next, string $menu, string $permission = 'view'): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            abort(403, 'Access denied.');
        }

        if (! $this->menuPermissions->can($user->role_id, $menu, $permission)) {
            abort(403, 'You do not have permission to perform this action.');
        }

        return $next($request);
    }
}
