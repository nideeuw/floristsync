<?php

namespace App\Services;

use App\Models\Menu;
use App\Models\MenuPermission;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class MenuPermissionService
{
    private const CACHE_TTL = 3600;

    public function menuTree(int $roleId, string $moduleGroup): Collection
    {
        return Cache::remember(
            "menu_tree_{$roleId}_{$moduleGroup}",
            self::CACHE_TTL,
            fn() => $this->buildTree($roleId, $moduleGroup)
        );
    }

    public function can(int $roleId, string $urlLink, string $permission): bool
    {
        return Cache::remember(
            "menu_can_{$roleId}_{$urlLink}_{$permission}",
            self::CACHE_TTL,
            fn() => $this->queryPermission($roleId, $urlLink, $permission)
        );
    }

    public function permissionFor(int $roleId, string $urlLink): ?MenuPermission
    {
        return Cache::remember(
            "menu_perm_row_{$roleId}_{$urlLink}",
            self::CACHE_TTL,
            fn() => MenuPermission::query()
                ->whereHas('menu', fn($q) => $q->where('url_link', $urlLink)->where('is_active', true))
                ->where('role_id', $roleId)
                ->where('is_active', true)
                ->first()
        );
    }

    public static function flushCache(?int $roleId = null): void
    {
        Cache::flush();
    }

    private function buildTree(int $roleId, string $moduleGroup): Collection
    {
        $viewableMenuIds = MenuPermission::query()
            ->where('role_id', $roleId)
            ->where('is_view', true)
            ->where('is_active', true)
            ->pluck('menu_id');

        $menus = Menu::query()
            ->where('module_group', $moduleGroup)
            ->where('is_active', true)
            ->where(function ($q) use ($viewableMenuIds) {
                $q->whereIn('id', $viewableMenuIds)
                    ->orWhere(fn($q2) => $q2->whereNull('parent_id')
                        ->where('menu_behavior', 'group')
                        ->whereHas('children', fn($q3) => $q3->whereIn('id', $viewableMenuIds)));
            })
            ->orderBy('order_no')
            ->get();

        $parents = $menus->whereNull('parent_id')->values();
        $children = $menus->whereNotNull('parent_id')->groupBy('parent_id');

        return $parents->map(function (Menu $parent) use ($children) {
            $parent->setRelation(
                'children',
                ($children->get($parent->id) ?? collect())->sortBy('order_no')->values()
            );
            return $parent;
        });
    }

    private function queryPermission(int $roleId, string $urlLink, string $permission): bool
    {
        $column = 'is_' . $permission;
        $validColumns = ['is_view', 'is_add', 'is_edit', 'is_delete', 'is_approve', 'is_publish'];

        if (! in_array($column, $validColumns, true)) {
            return false;
        }

        return MenuPermission::query()
            ->whereHas('menu', fn($q) => $q->where('url_link', $urlLink)->where('is_active', true))
            ->where('role_id', $roleId)
            ->where('is_active', true)
            ->where($column, true)
            ->exists();
    }
}
