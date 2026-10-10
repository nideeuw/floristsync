<?php

namespace App\View\Composers;

use App\Models\Role;
use App\Services\MenuPermissionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MenuComposer
{
    public function __construct(private MenuPermissionService $menuPermissions) {}

    public function compose(View $view): void
    {
        $user = Auth::user();

        if (! $user || ! $user->role_id) {
            $view->with(['menuTree' => collect(), 'menuLinks' => collect()]);

            return;
        }

        $moduleGroup = match ($user->role?->role_name) {
            Role::CASHIER => 'POS',
            Role::MEMBER  => 'LANDING',
            default       => 'ADMIN_PANEL',
        };

        $tree = $this->present($this->menuPermissions->menuTree($user->role_id, $moduleGroup));

        $view->with([
            'menuTree'  => $tree,
            'menuLinks' => $this->leaves($tree),
        ]);
    }

    private function present(Collection $tree): Collection
    {
        $tree = $tree
            ->map(function (object $menu) {
                $this->resolveLink($menu);
                $menu->children = $menu->children
                    ->map(fn(object $child) => $this->resolveLink($child))
                    ->filter(fn(object $child) => $child->href !== null)
                    ->values();

                return $menu;
            })
            ->filter(fn(object $menu) => $menu->menu_behavior === 'group'
                ? $menu->children->isNotEmpty()
                : $menu->href !== null)
            ->values();

        $this->markActive($this->leaves($tree));

        $tree->each(function (object $menu) {
            if ($menu->menu_behavior === 'group') {
                $menu->active = $menu->children->contains('active', true);
            }
        });

        return $tree;
    }

    private function resolveLink(object $menu): object
    {
        $menu->href = ($menu->url_link && Route::has($menu->url_link)) ? route($menu->url_link) : null;
        $menu->active = false;

        return $menu;
    }

    private function leaves(Collection $tree): Collection
    {
        return $tree
            ->flatMap(fn(object $menu) => $menu->menu_behavior === 'group' ? $menu->children : collect([$menu]))
            ->values();
    }

    private function markActive(Collection $leaves): void
    {
        $current = Route::currentRouteName();

        if (! $current) {
            return;
        }

        $best = $leaves
            ->filter(fn(object $menu) => $this->matches($current, $menu->url_link))
            ->sortByDesc(fn(object $menu) => strlen($menu->url_link))
            ->first();

        if ($best) {
            $best->active = true;
        }
    }

    private function matches(string $current, string $link): bool
    {
        return $current === $link || Str::startsWith($current, Str::chopEnd($link, '.index') . '.');
    }
}
