<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\MenuPermission;
use App\Models\Role;
use App\Models\User;
use App\Services\MenuPermissionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RolesMenusSeeder extends Seeder
{
    public function run(): void
    {
        $roles = $this->seedRoles();
        $menus = $this->seedMenus();
        $this->seedPermissions($roles, $menus);
        $this->seedUsers($roles);

        MenuPermissionService::flushCache();
    }

    private function seedRoles(): array
    {
        $data = [
            Role::MEMBER          => 'Registered customer, can browse and place online orders',
            Role::ADMIN_ORDER     => 'Manages online orders, shipping, and cancellations',
            Role::ADMIN_INVENTORY => 'Manages product catalog and stock',
            Role::CASHIER         => 'Handles POS in-store transactions',
            Role::OWNER           => 'Dashboard, reports, staff accounts, store settings',
        ];

        $roles = [];
        foreach ($data as $name => $description) {
            $roles[$name] = Role::firstOrCreate(
                ['role_name' => $name],
                ['description' => $description]
            );
        }

        return $roles;
    }

    private function seedMenus(): array
    {
        $m = [];

        // Admin panel
        $m['dashboard'] = $this->menu('Dashboard', 'ADMIN_PANEL', 1, null, 'layout-dashboard', 'admin.dashboard');
        $m['admin_notifications'] = $this->menu('Notifications', 'ADMIN_PANEL', 2, null, 'bell', 'admin.notifications');

        $m['order_group']     = $this->menu('Order Management', 'ADMIN_PANEL', 10, null, 'shopping-bag', null, 'group');
        $m['orders']          = $this->menu('Online Orders', 'ADMIN_PANEL', 1, $m['order_group'], 'clipboard-list', 'admin.orders');
        $m['order_milestone'] = $this->menu('Order Milestones', 'ADMIN_PANEL', 2, $m['order_group'], 'list-checks', 'admin.orders.milestone');
        $m['order_shipping']  = $this->menu('Shipping & Tracking', 'ADMIN_PANEL', 3, $m['order_group'], 'truck', 'admin.orders.shipping');
        $m['order_cancel']    = $this->menu('Order Cancellations', 'ADMIN_PANEL', 4, $m['order_group'], 'circle-x', 'admin.orders.cancellations');

        $m['inventory_group'] = $this->menu('Inventory', 'ADMIN_PANEL', 20, null, 'boxes', null, 'group');
        $m['products']        = $this->menu('Product Catalog', 'ADMIN_PANEL', 1, $m['inventory_group'], 'tag', 'admin.products.index');
        $m['categories']      = $this->menu('Categories', 'ADMIN_PANEL', 2, $m['inventory_group'], 'folder', 'admin.categories.index');
        $m['stock']           = $this->menu('Stock', 'ADMIN_PANEL', 3, $m['inventory_group'], 'package', 'admin.stock.index');
        $m['restock']         = $this->menu('Restock', 'ADMIN_PANEL', 4, $m['inventory_group'], 'circle-plus', 'admin.stock.restock');
        $m['stock_adjust']    = $this->menu('Stock Adjustment', 'ADMIN_PANEL', 5, $m['inventory_group'], 'sliders-horizontal', 'admin.stock.adjust');
        $m['stock_history']   = $this->menu('Stock History', 'ADMIN_PANEL', 6, $m['inventory_group'], 'history', 'admin.stock.history');

        $m['owner_group']  = $this->menu('Business', 'ADMIN_PANEL', 30, null, 'briefcase', null, 'group');
        $m['reports']      = $this->menu('Reports & Analytics', 'ADMIN_PANEL', 1, $m['owner_group'], 'trending-up', 'admin.reports');
        $m['transactions'] = $this->menu('Transaction History', 'ADMIN_PANEL', 2, $m['owner_group'], 'receipt', 'admin.transactions');
        $m['staff']        = $this->menu('Staff Accounts', 'ADMIN_PANEL', 3, $m['owner_group'], 'users', 'admin.staff.index');
        $m['settings']     = $this->menu('Store Settings', 'ADMIN_PANEL', 4, $m['owner_group'], 'settings', 'admin.settings');

        // POS
        $m['pos_group']   = $this->menu('POS', 'POS', 10, null, 'monitor', null, 'group');
        $m['pos_sale']    = $this->menu('Sales', 'POS', 1, $m['pos_group'], 'shopping-cart', 'cashier.pos');
        $m['pos_history'] = $this->menu('Transaction History', 'POS', 2, $m['pos_group'], 'calendar-days', 'cashier.history');
        $m['pos_cancel']  = $this->menu('Cancel Transaction', 'POS', 3, $m['pos_group'], 'x', 'cashier.cancel');
        $m['pos_notifications'] = $this->menu('Notifications', 'POS', 4, $m['pos_group'], 'bell', 'cashier.notifications');

        // Landing page (member only)
        $m['member_group']   = $this->menu('Member', 'LANDING', 10, null, 'circle-user', null, 'group', 'topbar');
        $m['profile']        = $this->menu('Profile', 'LANDING', 1, $m['member_group'], 'user', 'profile.edit', 'link', 'topbar');
        $m['cart']           = $this->menu('Cart', 'LANDING', 2, $m['member_group'], 'shopping-cart', 'cart', 'link', 'topbar');
        $m['checkout']       = $this->menu('Checkout', 'LANDING', 3, $m['member_group'], 'credit-card', 'checkout', 'link', 'topbar');
        $m['orders_history'] = $this->menu('Order History', 'LANDING', 4, $m['member_group'], 'file-text', 'track', 'link', 'topbar');
        $m['notifications']  = $this->menu('Notifications', 'LANDING', 5, $m['member_group'], 'bell', 'notifications', 'link', 'topbar');

        return $m;
    }

    private function seedPermissions(array $roles, array $menus): void
    {
        // Flags: [view, add, edit, delete, approve, publish]
        $view = [1, 0, 0, 0, 0, 0];
        $crud = [1, 1, 1, 1, 0, 0];
        $add  = [1, 1, 0, 0, 0, 0];
        $edit = [1, 0, 1, 0, 0, 0];

        $matrix = [
            'dashboard'           => [Role::OWNER => $view, Role::ADMIN_ORDER => $view, Role::ADMIN_INVENTORY => $view],
            'admin_notifications' => [Role::ADMIN_ORDER => $edit, Role::ADMIN_INVENTORY => $edit],

            'orders'          => [Role::ADMIN_ORDER => $edit],
            'order_milestone' => [Role::ADMIN_ORDER => $view],
            'order_shipping'  => [Role::ADMIN_ORDER => $edit],
            'order_cancel'    => [Role::ADMIN_ORDER => [1, 0, 1, 0, 1, 0]],

            'products'        => [Role::ADMIN_INVENTORY => $crud],
            'categories'      => [Role::ADMIN_INVENTORY => $crud],
            'stock'           => [Role::ADMIN_INVENTORY => $view],
            'restock'         => [Role::ADMIN_INVENTORY => $add],
            'stock_adjust'    => [Role::ADMIN_INVENTORY => $add],
            'stock_history'   => [Role::ADMIN_INVENTORY => $view],

            'reports'         => [Role::OWNER => [1, 0, 0, 0, 0, 1]],
            'transactions'    => [Role::OWNER => $view],
            'staff'           => [Role::OWNER => $crud],
            'settings'        => [Role::OWNER => $edit],

            'pos_sale'          => [Role::CASHIER => $add],
            'pos_history'       => [Role::CASHIER => $view],
            'pos_notifications' => [Role::CASHIER => $edit],
            'pos_cancel'        => [
                Role::CASHIER     => $add,
                Role::OWNER       => [0, 0, 0, 0, 1, 0],
                Role::ADMIN_ORDER => [0, 0, 0, 0, 1, 0],
            ],

            'profile'         => [Role::MEMBER => [1, 0, 1, 1, 0, 0]],
            'cart'            => [Role::MEMBER => $crud],
            'checkout'        => [Role::MEMBER => $add],
            'orders_history'  => [Role::MEMBER => $view],
            'notifications'   => [Role::MEMBER => $edit],
        ];

        $flags = ['is_view', 'is_add', 'is_edit', 'is_delete', 'is_approve', 'is_publish'];

        foreach ($matrix as $key => $rolePerms) {
            foreach ($rolePerms as $roleName => $values) {
                $data = ['is_active' => true];
                foreach ($flags as $i => $flag) {
                    $data[$flag] = (bool) $values[$i];
                }

                MenuPermission::updateOrCreate(
                    ['menu_id' => $menus[$key]->id, 'role_id' => $roles[$roleName]->id],
                    $data
                );
            }
        }

        foreach ($matrix as $key => $rolePerms) {
            $allowedRoleIds = collect(array_keys($rolePerms))
                ->map(fn ($roleName) => $roles[$roleName]->id)
                ->all();

            MenuPermission::where('menu_id', $menus[$key]->id)
                ->whereNotIn('role_id', $allowedRoleIds)
                ->delete();
        }
    }

    private function seedUsers(array $roles): void
    {
        $users = [
            [Role::OWNER,           'Owner',           'owner@floristsync.test',           '08110000001'],
            [Role::ADMIN_ORDER,     'Admin Order',     'admin.order@floristsync.test',     '08110000002'],
            [Role::ADMIN_INVENTORY, 'Admin Inventory', 'admin.inventory@floristsync.test', '08110000003'],
            [Role::CASHIER,         'Cashier',         'cashier@floristsync.test',         '08110000004'],
            [Role::MEMBER,          'Member',          'member@floristsync.test',          '08110000005'],
        ];

        foreach ($users as [$roleName, $name, $email, $phone]) {
            User::firstOrCreate(
                ['email' => $email],
                [
                    'role_id'  => $roles[$roleName]->id,
                    'name'     => $name,
                    'password' => Hash::make('password'),
                    'phone'    => $phone,
                ]
            );
        }
    }

    private function menu(
        string $name,
        string $group,
        int $order,
        ?Menu $parent,
        string $icon,
        ?string $url = null,
        string $behavior = 'link',
        string $mode = 'sidebar',
    ): Menu {
        return Menu::updateOrCreate(
            ['menu_name' => $name, 'module_group' => $group, 'parent_id' => $parent?->id],
            [
                'order_no'      => $order,
                'menu_level'    => $parent ? 2 : 1,
                'menu_behavior' => $behavior,
                'menu_mode'     => $mode,
                'menu_icon'     => $icon,
                'url_link'      => $url,
                'is_active'     => true,
            ]
        );
    }
}
