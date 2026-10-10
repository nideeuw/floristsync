<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LoginController as AdminLoginController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Cashier\LoginController as CashierLoginController;
use App\Http\Controllers\Cashier\PosController;
use App\Http\Controllers\Cashier\TransactionController;
use App\Http\Controllers\Customer\CartController;
use App\Http\Controllers\Customer\CatalogController;
use App\Http\Controllers\Customer\CheckoutController;
use App\Http\Controllers\Customer\HomeController;
use App\Http\Controllers\Customer\TrackController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PlaceholderController;
use App\Http\Controllers\ProfileController;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// =====================================================================
// CUSTOMER AREA: login at /login (Breeze)
// =====================================================================
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog');
Route::get('/products/{product}', [CatalogController::class, 'show'])->name('products.show');

Route::get('/cart', [CartController::class, 'index'])->name('cart');
Route::post('/cart/{product}', [CartController::class, 'add'])->name('cart.add');
Route::patch('/cart/{product}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/{product}', [CartController::class, 'remove'])->name('cart.remove');

Route::get('/dashboard', function (Request $request) {
    return match ($request->user()->role?->role_name) {
        Role::ADMIN_ORDER, Role::ADMIN_INVENTORY, Role::OWNER => redirect()->route('admin.dashboard'),
        Role::CASHIER => redirect()->route('cashier.pos'),
        default => redirect()->route('home'),
    };
})->middleware('auth')->name('dashboard');

// Members only
Route::middleware(['auth', 'role:' . Role::MEMBER])->group(function () {

    Route::middleware('menu.perm:checkout')->group(function () {
        Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout');
        Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    });

    Route::get('/track', [TrackController::class, 'index'])
        ->middleware('menu.perm:track')->name('track');

    Route::middleware('menu.perm:notifications')->group(function () {
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
        Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
        Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead'])
            ->whereNumber('notification')->name('notifications.read');
    });

    // Breeze profile (customer account)
    Route::middleware('menu.perm:profile.edit')->group(function () {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });
});

// =====================================================================
// ADMIN AREA: login at /admin/login (admin order, admin inventory, owner)
// =====================================================================
Route::prefix('admin')->name('admin.')->group(function () {

    // Login door
    Route::get('login', [AdminLoginController::class, 'create'])->name('login');
    Route::post('login', [AdminLoginController::class, 'store'])->name('login.store');
    Route::post('logout', [AdminLoginController::class, 'destroy'])->middleware('auth')->name('logout');

    Route::middleware(['auth', 'role:' . implode(',', Role::ADMIN_PANEL_ROLES)])->group(function () {

        Route::get('/', [DashboardController::class, 'index'])
            ->middleware('menu.perm:admin.dashboard')
            ->name('dashboard');

        // ---- Notifications ----
        Route::middleware('menu.perm:admin.notifications')->group(function () {
            Route::get('notifications', [NotificationController::class, 'index'])->name('notifications');
            Route::patch('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
            Route::patch('notifications/{notification}/read', [NotificationController::class, 'markRead'])
                ->whereNumber('notification')->name('notifications.read');
        });

        // ---- Order management ----
        Route::middleware('menu.perm:admin.orders')->group(function () {
            Route::get('orders', [OrderController::class, 'index'])->name('orders');
            Route::get('orders/{transaction}', [OrderController::class, 'show'])->name('orders.show');
            Route::patch('orders/{transaction}/status', [OrderController::class, 'updateStatus'])->name('orders.status');
            Route::patch('orders/{transaction}/tracking-number', [OrderController::class, 'updateTrackingNumber'])->name('orders.tracking');
        });

        Route::get('order-milestones', PlaceholderController::class)
            ->middleware('menu.perm:admin.orders.milestone')->name('orders.milestone');
        Route::get('order-shipping', PlaceholderController::class)
            ->middleware('menu.perm:admin.orders.shipping')->name('orders.shipping');
        Route::get('order-cancellations', PlaceholderController::class)
            ->middleware('menu.perm:admin.orders.cancellations')->name('orders.cancellations');

        // ---- Inventory ----
        Route::middleware('menu.perm:admin.products.index')->group(function () {
            Route::resource('products', AdminProductController::class)->except('show');
            Route::patch('products/{product}/stock', [AdminProductController::class, 'adjustStock'])->name('products.stock');
        });

        Route::middleware('menu.perm:admin.categories.index')->group(function () {
            Route::resource('categories', CategoryController::class)->except('show');
        });

        Route::get('stock', PlaceholderController::class)
            ->middleware('menu.perm:admin.stock.index')->name('stock.index');
        Route::get('stock/restock', PlaceholderController::class)
            ->middleware('menu.perm:admin.stock.restock')->name('stock.restock');
        Route::get('stock/adjustments', PlaceholderController::class)
            ->middleware('menu.perm:admin.stock.adjust')->name('stock.adjust');
        Route::get('stock/history', PlaceholderController::class)
            ->middleware('menu.perm:admin.stock.history')->name('stock.history');

        // ---- Business (owner) ----
        Route::get('reports', [ReportController::class, 'index'])
            ->middleware('menu.perm:admin.reports')->name('reports');
        Route::get('transactions', PlaceholderController::class)
            ->middleware('menu.perm:admin.transactions')->name('transactions');
        Route::get('staff', PlaceholderController::class)
            ->middleware('menu.perm:admin.staff.index')->name('staff.index');
        Route::get('settings', PlaceholderController::class)
            ->middleware('menu.perm:admin.settings')->name('settings');
    });
});

// =====================================================================
// POS AREA: login at /pos/login (cashier only)
// =====================================================================
Route::prefix('pos')->name('cashier.')->group(function () {

    // Login door
    Route::get('login', [CashierLoginController::class, 'create'])->name('login');
    Route::post('login', [CashierLoginController::class, 'store'])->name('login.store');
    Route::post('logout', [CashierLoginController::class, 'destroy'])->middleware('auth')->name('logout');

    Route::middleware(['auth', 'role:' . implode(',', Role::POS_ROLES)])->group(function () {

        Route::get('/', [PosController::class, 'index'])
            ->middleware('menu.perm:cashier.pos')->name('pos');

        Route::middleware('menu.perm:cashier.history')->group(function () {
            Route::get('history', [TransactionController::class, 'index'])->name('history');
            Route::get('receipt/{transaction}', [TransactionController::class, 'receipt'])->name('receipt');
        });

        Route::get('cancel', PlaceholderController::class)
            ->middleware('menu.perm:cashier.cancel')->name('cancel');

        Route::middleware('menu.perm:cashier.notifications')->group(function () {
            Route::get('notifications', [NotificationController::class, 'index'])->name('notifications');
            Route::patch('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
            Route::patch('notifications/{notification}/read', [NotificationController::class, 'markRead'])
                ->whereNumber('notification')->name('notifications.read');
        });
    });
});

require __DIR__ . '/auth.php';
