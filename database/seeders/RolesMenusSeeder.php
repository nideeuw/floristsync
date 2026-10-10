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
        $m['dashboard'] = $this->menu('Dashboard', 'ADMIN_PANEL', 1, null, 'heroicon-o-home', 'admin.dashboard');

        $m['order_group']     = $this->menu('Order Management', 'ADMIN_PANEL', 10, null, 'heroicon-o-shopping-bag', null, 'group');
        $m['orders']          = $this->menu('Online Orders', 'ADMIN_PANEL', 1, $m['order_group'], 'heroicon-o-clipboard-document-list', 'admin.orders');
        $m['order_milestone'] = $this->menu('Order Milestones', 'ADMIN_PANEL', 2, $m['order_group'], 'heroicon-o-chart-bar', 'admin.orders.milestone');
        $m['order_shipping']  = $this->menu('Shipping & Tracking', 'ADMIN_PANEL', 3, $m['order_group'], 'heroicon-o-truck', 'admin.orders.shipping');
        $m['order_cancel']    = $this->menu('Order Cancellations', 'ADMIN_PANEL', 4, $m['order_group'], 'heroicon-o-x-circle', 'admin.orders.cancellations');

        $m['inventory_group'] = $this->menu('Inventory', 'ADMIN_PANEL', 20, null, 'heroicon-o-squares-2x2', null, 'group');
        $m['products']        = $this->menu('Product Catalog', 'ADMIN_PANEL', 1, $m['inventory_group'], 'heroicon-o-tag', 'admin.products.index');
        $m['categories']      = $this->menu('Categories', 'ADMIN_PANEL', 2, $m['inventory_group'], 'heroicon-o-folder', 'admin.categories.index');
        $m['stock']           = $this->menu('Stock', 'ADMIN_PANEL', 3, $m['inventory_group'], 'heroicon-o-archive-box', 'admin.stock.index');
        $m['restock']         = $this->menu('Restock', 'ADMIN_PANEL', 4, $m['inventory_group'], 'heroicon-o-plus-circle', 'admin.stock.restock');
        $m['stock_adjust']    = $this->menu('Stock Adjustment', 'ADMIN_PANEL', 5, $m['inventory_group'], 'heroicon-o-adjustments-horizontal', 'admin.stock.adjust');
        $m['stock_history']   = $this->menu('Stock History', 'ADMIN_PANEL', 6, $m['inventory_group'], 'heroicon-o-clock', 'admin.stock.history');

        $m['owner_group']  = $this->menu('Business', 'ADMIN_PANEL', 30, null, 'heroicon-o-briefcase', null, 'group');
        $m['reports']      = $this->menu('Reports & Analytics', 'ADMIN_PANEL', 1, $m['owner_group'], 'heroicon-o-document-chart-bar', 'admin.reports');
        $m['transactions'] = $this->menu('Transaction History', 'ADMIN_PANEL', 2, $m['owner_group'], 'heroicon-o-receipt-refund', 'admin.transactions');
        $m['staff']        = $this->menu('Staff Accounts', 'ADMIN_PANEL', 3, $m['owner_group'], 'heroicon-o-users', 'admin.staff.index');
        $m['settings']     = $this->menu('Store Settings', 'ADMIN_PANEL', 4, $m['owner_group'], 'heroicon-o-cog-6-tooth', 'admin.settings');

        // POS
        $m['pos_group']   = $this->menu('POS', 'POS', 10, null, 'heroicon-o-computer-desktop', null, 'group');
        $m['pos_sale']    = $this->menu('Sales', 'POS', 1, $m['pos_group'], 'heroicon-o-shopping-cart', 'cashier.pos');
        $m['pos_history'] = $this->menu('Transaction History', 'POS', 2, $m['pos_group'], 'heroicon-o-calendar-days', 'cashier.history');
        $m['pos_cancel']  = $this->menu('Cancel Transaction', 'POS', 3, $m['pos_group'], 'heroicon-o-x-mark', 'cashier.cancel');

        // Landing page (member only)
        $m['member_group']   = $this->menu('Member', 'LANDING', 10, null, 'heroicon-o-user-circle', null, 'group', 'topbar');
        $m['profile']        = $this->menu('Profile', 'LANDING', 1, $m['member_group'], 'heroicon-o-user', 'profile.edit', 'link', 'topbar');
        $m['cart']           = $this->menu('Cart', 'LANDING', 2, $m['member_group'], 'heroicon-o-shopping-cart', 'cart', 'link', 'topbar');
        $m['checkout']       = $this->menu('Checkout', 'LANDING', 3, $m['member_group'], 'heroicon-o-credit-card', 'checkout', 'link', 'topbar');
        $m['orders_history'] = $this->menu('Order History', 'LANDING', 4, $m['member_group'], 'heroicon-o-document-text', 'track', 'link', 'topbar');
        $m['notifications']  = $this->menu('Notifications', 'LANDING', 5, $m['member_group'], 'heroicon-o-bell', 'notifications', 'link', 'topbar');

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
            'dashboard'       => [Role::ADMIN_ORDER => $view, Role::ADMIN_INVENTORY => $view, Role::OWNER => $view],

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

            'pos_sale'        => [Role::CASHIER => $add],
            'pos_history'     => [Role::CASHIER => $view],
            'pos_cancel'      => [
                Role::CASHIER     => $add,
                Role::OWNER       => [0, 0, 0, 0, 1, 0],
                Role::ADMIN_ORDER => [0, 0, 0, 0, 1, 0],
            ],

            'profile'         => [Role::MEMBER => [1, 0, 1, 1, 0, 0]],
            'cart'            => [Role::MEMBER => $crud],
            'checkout'        => [Role::MEMBER => $add],
            'orders_history'  => [Role::MEMBER => $view],
            'notifications'   => [Role::MEMBER => $view],
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
