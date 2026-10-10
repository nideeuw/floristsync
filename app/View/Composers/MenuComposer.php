<?php

namespace App\View\Composers;

use App\Models\Role;
use App\Services\MenuPermissionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MenuComposer
{
    public function __construct(private MenuPermissionService $menuPermissions) {}

    public function compose(View $view): void
    {
        $user = Auth::user();

        if (! $user || ! $user->role_id) {
            $view->with('sidebarMenus', collect());
            return;
        }

        $moduleGroup = match ($user->role?->role_name) {
            Role::CASHIER => 'POS',
            Role::MEMBER  => 'LANDING',
            default       => 'ADMIN_PANEL',
        };

        $view->with('sidebarMenus', $this->menuPermissions->menuTree($user->role_id, $moduleGroup));
        $view->with('menuModuleGroup', $moduleGroup);
    }
}
