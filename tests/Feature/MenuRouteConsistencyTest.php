<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\MenuPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\InteractsWithSeededRoles;
use Tests\TestCase;

/**
 * Consistency between seeded menus / menu_permissions and real named routes.
 *
 * Known mismatches (reported, not fixed here):
 * 1. Menu `cart` (url_link `cart`) has full CRUD for member, but cart routes have
 *    no menu.perm middleware — the permission row is never enforced.
 * 2. Menu `admin.notifications` is not granted to owner, while owner is in the
 *    admin panel role group — owner gets 403 on admin notification pages.
 * 3. cashier.receipt is protected by role only, not menu.perm (inconsistent POS).
 * 4. orders_history stores url_link `track` (route name of the order-history page),
 *    which is valid but inconsistent with admin.* menus that store full dotted names.
 */
class MenuRouteConsistencyTest extends TestCase
{
    use RefreshDatabase, InteractsWithSeededRoles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndMenus();
    }

    public function test_every_menu_url_link_is_a_real_route_name(): void
    {
        $menus = Menu::query()->whereNotNull('url_link')->get();

        $this->assertNotEmpty($menus, 'Expected seeded menus with url_link.');

        foreach ($menus as $menu) {
            $this->assertTrue(
                Route::has($menu->url_link),
                "Menu [{$menu->menu_name}] url_link [{$menu->url_link}] is not a registered route name.",
            );
        }
    }

    public function test_every_menu_permission_row_maps_to_a_real_route(): void
    {
        $rows = MenuPermission::query()->with('menu')->get();

        $this->assertNotEmpty($rows, 'Expected seeded menu_permissions rows.');

        foreach ($rows as $row) {
            $urlLink = $row->menu?->url_link;

            $this->assertNotNull(
                $urlLink,
                "MenuPermission [{$row->id}] points at a menu without url_link.",
            );

            $this->assertTrue(
                Route::has($urlLink),
                "MenuPermission [{$row->id}] menu url_link [{$urlLink}] is not a registered route name.",
            );
        }
    }

    public function test_every_menu_perm_middleware_argument_has_a_menu_row(): void
    {
        $args = $this->menuPermArguments();

        $this->assertNotEmpty($args, 'Expected at least one menu.perm middleware on routes.');

        foreach ($args as $arg) {
            $exists = Menu::query()
                ->where('url_link', $arg)
                ->where('is_active', true)
                ->exists();

            $this->assertTrue(
                $exists,
                "menu.perm:{$arg} is used on a route but no active Menu has url_link={$arg}.",
            );
        }
    }

    public function test_report_known_mismatches(): void
    {
        // Mismatch 1: cart menu is seeded but cart routes have no menu.perm.
        $cartRoutesWithoutMenuPerm = collect(Route::getRoutes())
            ->filter(fn ($route) => in_array($route->getName(), ['cart', 'cart.add', 'cart.update', 'cart.remove'], true))
            ->filter(fn ($route) => ! $this->routeUsesMenuPerm($route))
            ->pluck('name')
            ->all();

        $this->assertNotEmpty(
            $cartRoutesWithoutMenuPerm,
            'Expected cart routes to lack menu.perm (known mismatch #1). If someone added menu.perm, update this test and the mismatch report.',
        );

        // Mismatch 2: owner has no admin.notifications permission while in ADMIN_PANEL_ROLES.
        $ownerHasAdminNotifications = MenuPermission::query()
            ->whereHas('menu', fn ($q) => $q->where('url_link', 'admin.notifications'))
            ->whereHas('role', fn ($q) => $q->where('role_name', \App\Models\Role::OWNER))
            ->exists();

        $this->assertFalse(
            $ownerHasAdminNotifications,
            'Known mismatch #2: owner unexpectedly has admin.notifications permission. Update report if seed changed.',
        );

        // Mismatch 3: cashier.receipt has no menu.perm.
        $receipt = Route::getRoutes()->getByName('cashier.receipt');
        $this->assertNotNull($receipt);
        $this->assertFalse(
            $this->routeUsesMenuPerm($receipt),
            'Known mismatch #3: cashier.receipt unexpectedly has menu.perm. Update report if middleware changed.',
        );
    }

    /**
     * @return list<string>
     */
    private function menuPermArguments(): array
    {
        $args = [];

        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (is_string($middleware) && str_starts_with($middleware, 'menu.perm:')) {
                    $args[] = explode(',', substr($middleware, strlen('menu.perm:')))[0];
                }
            }
        }

        return array_values(array_unique($args));
    }

    private function routeUsesMenuPerm(\Illuminate\Routing\Route $route): bool
    {
        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'menu.perm')) {
                return true;
            }
        }

        return false;
    }
}
