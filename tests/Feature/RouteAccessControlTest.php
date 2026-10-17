<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithSeededRoles;
use Tests\TestCase;

/**
 * Access control matrix for every web route protected by `role:` and/or `menu.perm:`.
 *
 * For each route: guest must be redirected to the login door; each of the five roles
 * either gets through (200/redirect) when allowed by RoleMiddleware + MenuPermission
 * seed data, or 403 when not.
 *
 * Routes whose controller still uses RendersPlaceholder or abort(501) belong in
 * INCOMPLETE_ROUTES (key = route name, value = owner name) and are marked incomplete
 * so unfinished pages do not fail the suite.
 *
 * MISMATCHES found (report only — do not fix app code here):
 * 1. Menu `cart` seeds full CRUD for member, but cart routes have no `menu.perm`
 *    (and no auth) — permission is never enforced.
 * 2. Menu `admin.notifications` is granted only to admin_order + admin_inventory;
 *    owner passes role:admin_* but is denied by menu.perm on admin notification routes.
 * 3. Route cashier.receipt has role:cashier only (no menu.perm), unlike other POS routes.
 * 4. Menu `orders_history` uses url_link `track` (not a `track.*` index route name) —
 *    works, but naming is inconsistent with admin.* menus that store full index names.
 */
class RouteAccessControlTest extends TestCase
{
    use RefreshDatabase, InteractsWithSeededRoles;

    /** @var array<string, string> route name => owner name for unfinished placeholder/501 pages */
    private const INCOMPLETE_ROUTES = [
        // e.g. 'admin.something' => 'Justin',
    ];

    private const ALL_ROLES = [
        Role::MEMBER,
        Role::ADMIN_ORDER,
        Role::ADMIN_INVENTORY,
        Role::CASHIER,
        Role::OWNER,
    ];

    private Category $category;

    private Product $product;

    private Transaction $adminTransaction;

    private Transaction $posTransaction;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndMenus();

        $this->category = Category::factory()->create();
        $this->product = Product::factory()->create(['category_id' => $this->category->id]);
        $this->adminTransaction = Transaction::factory()->processing()->create();
        $this->posTransaction = Transaction::factory()->create([
            'transaction_type' => Transaction::TYPE_POS,
            'status' => Transaction::COMPLETED,
            'cashier_id' => $this->userForRole(Role::CASHIER)->id,
        ]);

        $this->staff = User::factory()->create([
            'role_id' => $this->roleId(Role::CASHIER),
            'is_active' => true,
        ]);

        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createSnapToken')->andReturn('snap-token-test')->byDefault();
            $mock->shouldReceive('getStatus')->andReturn(null)->byDefault();
            $mock->shouldReceive('cancelInvoice')->andReturnNull()->byDefault();
            $mock->shouldReceive('cancelTransaction')->andReturnNull()->byDefault();
            $mock->shouldReceive('readNotification')->andReturn([])->byDefault();
        });
    }

    /**
     * @return array<string, array{method: string, allowedRoles: string[], roleMiddleware: string[], menu: string|null, permission: string|null, params: array<string, string>}>
     */
    public static function protectedRoutes(): array
    {
        $adminPanel = [Role::ADMIN_ORDER, Role::ADMIN_INVENTORY, Role::OWNER];

        $routes = [
            // ---- Member area (role:member) ----
            'checkout' => [
                'GET', [Role::MEMBER], [Role::MEMBER], 'checkout', 'view', [],
            ],
            'checkout.store' => [
                'POST', [Role::MEMBER], [Role::MEMBER], 'checkout', 'add', [],
            ],
            'track' => [
                'GET', [Role::MEMBER], [Role::MEMBER], 'track', 'view', [],
            ],
            'track.invoice' => [
                'GET', [Role::MEMBER], [Role::MEMBER], 'track', 'view',
                ['transaction' => 'member_online'],
            ],
            'track.pay' => [
                'GET', [Role::MEMBER], [Role::MEMBER], 'track', 'view',
                ['transaction' => 'member_processing'],
            ],
            'track.cancel' => [
                'POST', [Role::MEMBER], [Role::MEMBER], 'track', 'edit',
                ['transaction' => 'member_processing'],
            ],
            'notifications' => [
                'GET', [Role::MEMBER], [Role::MEMBER], 'notifications', 'view', [],
            ],
            'notifications.read-all' => [
                'PATCH', [Role::MEMBER], [Role::MEMBER], 'notifications', 'edit', [],
            ],
            'notifications.read' => [
                'PATCH', [Role::MEMBER], [Role::MEMBER], 'notifications', 'edit',
                ['notification' => 'own'],
            ],
            'profile.edit' => [
                'GET', [Role::MEMBER], [Role::MEMBER], 'profile.edit', 'view', [],
            ],
            'profile.update' => [
                'PATCH', [Role::MEMBER], [Role::MEMBER], 'profile.edit', 'edit', [],
            ],
            'profile.destroy' => [
                'DELETE', [Role::MEMBER], [Role::MEMBER], 'profile.edit', 'delete', [],
            ],

            // ---- Admin panel (role: admin_order,admin_inventory,owner) ----
            'admin.dashboard' => [
                'GET', $adminPanel, $adminPanel, 'admin.dashboard', 'view', [],
            ],
            'admin.notifications' => [
                'GET', [Role::ADMIN_ORDER, Role::ADMIN_INVENTORY], $adminPanel,
                'admin.notifications', 'view', [],
            ],
            'admin.notifications.read-all' => [
                'PATCH', [Role::ADMIN_ORDER, Role::ADMIN_INVENTORY], $adminPanel,
                'admin.notifications', 'edit', [],
            ],
            'admin.notifications.read' => [
                'PATCH', [Role::ADMIN_ORDER, Role::ADMIN_INVENTORY], $adminPanel,
                'admin.notifications', 'edit', ['notification' => 'own'],
            ],
            'admin.orders' => [
                'GET', [Role::ADMIN_ORDER], $adminPanel, 'admin.orders', 'view', [],
            ],
            'admin.orders.show' => [
                'GET', [Role::ADMIN_ORDER], $adminPanel, 'admin.orders', 'view',
                ['transaction' => 'any'],
            ],
            'admin.orders.status' => [
                'PATCH', [Role::ADMIN_ORDER], $adminPanel, 'admin.orders', 'edit',
                ['transaction' => 'any'],
            ],
            'admin.orders.tracking' => [
                'PATCH', [Role::ADMIN_ORDER], $adminPanel, 'admin.orders', 'edit',
                ['transaction' => 'any'],
            ],
            'admin.orders.milestone' => [
                'GET', [Role::ADMIN_ORDER], $adminPanel, 'admin.orders.milestone', 'view', [],
            ],
            'admin.orders.shipping' => [
                'GET', [Role::ADMIN_ORDER], $adminPanel, 'admin.orders.shipping', 'view', [],
            ],
            'admin.orders.cancellations' => [
                'GET', [Role::ADMIN_ORDER], $adminPanel, 'admin.orders.cancellations', 'view', [],
            ],
            'admin.orders.cancellations.approve' => [
                'PATCH', [Role::ADMIN_ORDER], $adminPanel, 'admin.orders.cancellations', 'approve',
                ['transaction' => 'any'],
            ],
            'admin.orders.cancellations.reject' => [
                'PATCH', [Role::ADMIN_ORDER], $adminPanel, 'admin.orders.cancellations', 'approve',
                ['transaction' => 'any'],
            ],
            'admin.products.index' => [
                'GET', [Role::ADMIN_INVENTORY], $adminPanel, 'admin.products.index', 'view', [],
            ],
            'admin.products.create' => [
                'GET', [Role::ADMIN_INVENTORY], $adminPanel, 'admin.products.index', 'view', [],
            ],
            'admin.products.store' => [
                'POST', [Role::ADMIN_INVENTORY], $adminPanel, 'admin.products.index', 'add', [],
            ],
            'admin.products.edit' => [
                'GET', [Role::ADMIN_INVENTORY], $adminPanel, 'admin.products.index', 'view',
                ['product' => 'any'],
            ],
            'admin.products.update' => [
                'PUT', [Role::ADMIN_INVENTORY], $adminPanel, 'admin.products.index', 'edit',
                ['product' => 'any'],
            ],
            'admin.products.stock' => [
                'PATCH', [Role::ADMIN_INVENTORY], $adminPanel, 'admin.products.index', 'edit',
                ['product' => 'any'],
            ],
            'admin.products.toggle' => [
                'PATCH', [Role::ADMIN_INVENTORY], $adminPanel, 'admin.products.index', 'edit',
                ['product' => 'any'],
            ],
            'admin.categories.index' => [
                'GET', [Role::ADMIN_INVENTORY], $adminPanel, 'admin.categories.index', 'view', [],
            ],
            'admin.categories.create' => [
                'GET', [Role::ADMIN_INVENTORY], $adminPanel, 'admin.categories.index', 'view', [],
            ],
            'admin.categories.store' => [
                'POST', [Role::ADMIN_INVENTORY], $adminPanel, 'admin.categories.index', 'add', [],
            ],
            'admin.categories.edit' => [
                'GET', [Role::ADMIN_INVENTORY], $adminPanel, 'admin.categories.index', 'view',
                ['category' => 'any'],
            ],
            'admin.categories.update' => [
                'PUT', [Role::ADMIN_INVENTORY], $adminPanel, 'admin.categories.index', 'edit',
                ['category' => 'any'],
            ],
            'admin.categories.toggle' => [
                'PATCH', [Role::ADMIN_INVENTORY], $adminPanel, 'admin.categories.index', 'edit',
                ['category' => 'any'],
            ],
            'admin.stock.index' => [
                'GET', [Role::ADMIN_INVENTORY], $adminPanel, 'admin.stock.index', 'view', [],
            ],
            'admin.stock.restock' => [
                'GET', [Role::ADMIN_INVENTORY], $adminPanel, 'admin.stock.restock', 'view', [],
            ],
            'admin.stock.restock.store' => [
                'POST', [Role::ADMIN_INVENTORY], $adminPanel, 'admin.stock.restock', 'add', [],
            ],
            'admin.stock.adjust' => [
                'GET', [Role::ADMIN_INVENTORY], $adminPanel, 'admin.stock.adjust', 'view', [],
            ],
            'admin.stock.adjust.store' => [
                'POST', [Role::ADMIN_INVENTORY], $adminPanel, 'admin.stock.adjust', 'add', [],
            ],
            'admin.stock.history' => [
                'GET', [Role::ADMIN_INVENTORY], $adminPanel, 'admin.stock.history', 'view', [],
            ],
            'admin.reports' => [
                'GET', [Role::OWNER], $adminPanel, 'admin.reports', 'view', [],
            ],
            'admin.reports.export.pdf' => [
                'GET', [Role::OWNER], $adminPanel, 'admin.reports', 'view', [],
            ],
            'admin.reports.export.excel' => [
                'GET', [Role::OWNER], $adminPanel, 'admin.reports', 'view', [],
            ],
            'admin.transactions' => [
                'GET', [Role::OWNER], $adminPanel, 'admin.transactions', 'view', [],
            ],
            'admin.transactions.show' => [
                'GET', [Role::OWNER], $adminPanel, 'admin.transactions', 'view',
                ['transaction' => 'any'],
            ],
            'admin.staff.index' => [
                'GET', [Role::OWNER], $adminPanel, 'admin.staff.index', 'view', [],
            ],
            'admin.staff.create' => [
                'GET', [Role::OWNER], $adminPanel, 'admin.staff.index', 'view', [],
            ],
            'admin.staff.store' => [
                'POST', [Role::OWNER], $adminPanel, 'admin.staff.index', 'add', [],
            ],
            'admin.staff.edit' => [
                'GET', [Role::OWNER], $adminPanel, 'admin.staff.index', 'view',
                ['staff' => 'any'],
            ],
            'admin.staff.update' => [
                'PUT', [Role::OWNER], $adminPanel, 'admin.staff.index', 'edit',
                ['staff' => 'any'],
            ],
            'admin.staff.destroy' => [
                'DELETE', [Role::OWNER], $adminPanel, 'admin.staff.index', 'delete',
                ['staff' => 'any'],
            ],
            'admin.settings' => [
                'GET', [Role::OWNER], $adminPanel, 'admin.settings', 'view', [],
            ],
            'admin.settings.update' => [
                'PATCH', [Role::OWNER], $adminPanel, 'admin.settings', 'edit', [],
            ],

            // ---- POS (role:cashier) ----
            'cashier.pos' => [
                'GET', [Role::CASHIER], [Role::CASHIER], 'cashier.pos', 'view', [],
            ],
            'cashier.receipt' => [
                'GET', [Role::CASHIER], [Role::CASHIER], null, null,
                ['transaction' => 'pos'],
            ],
            'cashier.history' => [
                'GET', [Role::CASHIER], [Role::CASHIER], 'cashier.history', 'view', [],
            ],
            'cashier.cancel' => [
                'GET', [Role::CASHIER], [Role::CASHIER], 'cashier.cancel', 'view', [],
            ],
            'cashier.cancel.store' => [
                'POST', [Role::CASHIER], [Role::CASHIER], 'cashier.cancel', 'add',
                ['transaction' => 'pos'],
            ],
        ];

        $provider = [];
        foreach ($routes as $name => [$method, $allowed, $roleMw, $menu, $permission, $params]) {
            $provider[$name] = [
                'routeName' => $name,
                'method' => $method,
                'allowedRoles' => $allowed,
                'roleMiddleware' => $roleMw,
                'menu' => $menu,
                'permission' => $permission,
                'params' => $params,
            ];
        }

        return $provider;
    }

    #[DataProvider('protectedRoutes')]
    public function test_access_control(
        string $routeName,
        string $method,
        array $allowedRoles,
        array $roleMiddleware,
        ?string $menu,
        ?string $permission,
        array $params,
    ): void {
        $name = $routeName;

        if (isset(self::INCOMPLETE_ROUTES[$name])) {
            $this->markTestIncomplete(sprintf(
                'Route %s is still owned by %s (placeholder / not implemented).',
                $name,
                self::INCOMPLETE_ROUTES[$name],
            ));
        }

        $this->assertTrue(Route::has($name), "Route [{$name}] is not registered.");

        // ---- Guest: redirected to the correct login door ----
        // Guest only needs a URL that matches the route pattern — middleware
        // redirects before controllers run, so placeholder IDs are enough.
        $guestUri = $this->uriFor($name, $this->placeholderParams($params));
        $guestResponse = $this->{$this->httpVerb($method)}($guestUri);
        $guestResponse->assertRedirect($this->loginDoorFor($guestUri));

        // ---- Each role ----
        foreach (self::ALL_ROLES as $role) {
            $user = $this->userForRole($role);
            $uri = $this->uriFor($name, $this->paramsFor($params, $user));

            $response = $this->actingAs($user)->{$this->httpVerb($method)}($uri);

            if (in_array($role, $allowedRoles, true)) {
                $allowedStatuses = [200, 201, 301, 302, 303, 307, 308];
                $this->assertContains(
                    $response->getStatusCode(),
                    $allowedStatuses,
                    sprintf(
                        'Allowed role [%s] expected 200/redirect on %s %s, got %d. Body: %s',
                        $role,
                        $method,
                        $uri,
                        $response->getStatusCode(),
                        mb_substr($response->getContent() ?? '', 0, 500),
                    ),
                );
            } else {
                $response->assertForbidden(sprintf(
                    'Role [%s] should be forbidden on %s %s (allowed: %s).',
                    $role,
                    $method,
                    $uri,
                    implode(', ', $allowedRoles) ?: 'none',
                ));
            }

            $this->app['auth']->guard('web')->logout();
        }
    }

    /**
     * Every menu.perm argument used by routes must have a seeded menu row.
     */
    public function test_every_menu_perm_argument_has_a_seeded_menu(): void
    {
        $args = [];

        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (is_string($middleware) && str_starts_with($middleware, 'menu.perm:')) {
                    $args[] = explode(',', substr($middleware, strlen('menu.perm:')))[0];
                }
            }
        }

        $args = array_values(array_unique($args));
        $this->assertNotEmpty($args, 'Expected at least one menu.perm middleware usage.');

        $seeded = \App\Models\Menu::query()->whereNotNull('url_link')->pluck('url_link')->all();

        foreach ($args as $arg) {
            $this->assertContains($arg, $seeded, "menu.perm:{$arg} has no Menu row with url_link={$arg}.");
        }
    }

    private function httpVerb(string $method): string
    {
        return match (strtoupper($method)) {
            'GET' => 'get',
            'POST' => 'post',
            'PUT' => 'put',
            'PATCH' => 'patch',
            'DELETE' => 'delete',
            default => throw new \InvalidArgumentException("Unsupported method {$method}"),
        };
    }

    /**
     * @param  array<string, string>  $kinds
     * @return array<string, int>
     */
    private function placeholderParams(array $kinds): array
    {
        $params = [];

        foreach (array_keys($kinds) as $key) {
            $params[$key] = 1;
        }

        return $params;
    }

    /**
     * @param  array<string, string>  $kinds
     * @return array<string, int|string>
     */
    private function paramsFor(array $kinds, ?User $user): array
    {
        $params = [];

        foreach ($kinds as $key => $kind) {
            $params[$key] = match ($kind) {
                'any' => $this->paramId($key),
                'member_online', 'member_processing' => $this->memberOwnedTransaction($user, $kind)->id,
                'pos' => $this->posTransaction->id,
                'own' => $this->ownNotification($user)->id,
                default => throw new \InvalidArgumentException("Unknown fixture [{$kind}] for [{$key}]"),
            };
        }

        return $params;
    }

    private function paramId(string $key): int
    {
        return match ($key) {
            'transaction' => $this->adminTransaction->id,
            'product' => $this->product->id,
            'category' => $this->category->id,
            'staff' => $this->staff->id,
            default => throw new \InvalidArgumentException("Unknown param [{$key}]"),
        };
    }

    private function memberOwnedTransaction(?User $user, string $kind): Transaction
    {
        if ($user === null) {
            return $this->adminTransaction;
        }

        $status = $kind === 'member_processing'
            ? Transaction::PROCESSING
            : Transaction::PROCESSING;

        return Transaction::factory()->create([
            'user_id' => $user->id,
            'transaction_type' => Transaction::TYPE_ONLINE,
            'status' => $status,
        ]);
    }

    private function ownNotification(User $user): AppNotification
    {
        return AppNotification::create([
            'user_id' => $user->id,
            'title' => 'Test notification',
            'message' => 'Access control fixture',
            'type' => 'info',
            'is_read' => false,
        ]);
    }

    private function uriFor(string $routeName, array $params = []): string
    {
        return route($routeName, $params);
    }

    private function loginDoorFor(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: $uri;

        if (str_starts_with($path, '/admin')) {
            return route('admin.login');
        }

        if (str_starts_with($path, '/pos')) {
            return route('cashier.login');
        }

        return route('login');
    }
}
