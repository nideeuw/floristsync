<?php

namespace App\Services;

use App\Models\Menu;
use App\Models\MenuPermission;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class MenuPermissionService
{
    private const CACHE_TTL = 3600;

    private const MENU_FIELDS = [
        'id',
        'menu_name',
        'parent_id',
        'order_no',
        'menu_level',
        'menu_behavior',
        'module_group',
        'menu_mode',
        'menu_icon',
        'url_link',
    ];

    private const PERMISSION_FIELDS = [
        'is_view',
        'is_add',
        'is_edit',
        'is_delete',
        'is_approve',
        'is_publish',
    ];

    public function menuTree(int $roleId, string $moduleGroup): Collection
    {
        $tree = Cache::remember(
            "menu_tree_{$roleId}_{$moduleGroup}",
            self::CACHE_TTL,
            fn() => $this->buildTree($roleId, $moduleGroup)
        );

        return collect($tree)->map(function (array $parent) {
            $parent['children'] = collect($parent['children'])->map(fn(array $child) => (object) $child);

            return (object) $parent;
        });
    }

    public function can(int $roleId, string $urlLink, string $permission): bool
    {
        return Cache::remember(
            "menu_can_{$roleId}_{$urlLink}_{$permission}",
            self::CACHE_TTL,
            fn() => $this->queryPermission($roleId, $urlLink, $permission)
        );
    }

    public function permissionFor(int $roleId, string $urlLink): ?array
    {
        return Cache::remember(
            "menu_perm_row_{$roleId}_{$urlLink}",
            self::CACHE_TTL,
            fn() => MenuPermission::query()
                ->whereHas('menu', fn($q) => $q->where('url_link', $urlLink)->where('is_active', true))
                ->where('role_id', $roleId)
                ->where('is_active', true)
                ->first()
                ?->only(self::PERMISSION_FIELDS)
        );
    }

    public static function flushCache(?int $roleId = null): void
    {
        Cache::flush();
    }

    private function buildTree(int $roleId, string $moduleGroup): array
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

        $children = $menus->whereNotNull('parent_id')->groupBy('parent_id');

        return $menus->whereNull('parent_id')
            ->map(function (Menu $parent) use ($children) {
                $row = $parent->only(self::MENU_FIELDS);
                $row['children'] = ($children->get($parent->id) ?? collect())
                    ->sortBy('order_no')
                    ->map(fn(Menu $child) => $child->only(self::MENU_FIELDS))
                    ->values()
                    ->all();

                return $row;
            })
            ->values()
            ->all();
    }

    private function queryPermission(int $roleId, string $urlLink, string $permission): bool
    {
        $column = 'is_' . $permission;

        if (! in_array($column, self::PERMISSION_FIELDS, true)) {
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
