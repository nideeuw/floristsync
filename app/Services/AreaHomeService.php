<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Route;

class AreaHomeService
{
    public function __construct(private MenuPermissionService $menus) {}

    public function routeFor(User $user): string
    {
        $module = match (true) {
            $user->hasRole(...Role::ADMIN_PANEL_ROLES) => 'ADMIN_PANEL',
            $user->hasRole(...Role::POS_ROLES) => 'POS',
            default => null,
        };

        if ($module === null || ! $user->role_id) {
            return 'home';
        }

        foreach ($this->menus->menuTree($user->role_id, $module) as $menu) {
            $leaves = $menu->menu_behavior === 'group' ? $menu->children : collect([$menu]);

            foreach ($leaves as $leaf) {
                if ($leaf->url_link && Route::has($leaf->url_link)) {
                    return $leaf->url_link;
                }
            }
        }

        return 'home';
    }
}
