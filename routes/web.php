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
use App\Http\Controllers\ProfileController;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// =====================================================================
// CUSTOMER AREA: login at /login (Breeze), members only
// =====================================================================
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog');
Route::get('/products/{product}', [CatalogController::class, 'show'])->name('products.show');

Route::get('/cart', [CartController::class, 'index'])->name('cart');
Route::post('/cart/{product}', [CartController::class, 'add'])->name('cart.add');
Route::patch('/cart/{product}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/{product}', [CartController::class, 'remove'])->name('cart.remove');

Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');

Route::get('/track', [TrackController::class, 'index'])->name('track');

// Breeze redirects here after login.
Route::get('/dashboard', function (Request $request) {
    return match ($request->user()->role?->role_name) {
        Role::ADMIN, Role::OWNER => redirect()->route('admin.dashboard'),
        Role::CASHIER => redirect()->route('cashier.pos'),
        default => redirect()->route('home'),
    };
})->middleware('auth')->name('dashboard');

// Breeze profile (customer account)
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// =====================================================================
// ADMIN AREA: login at /admin/login (admin and owner)
// =====================================================================
Route::prefix('admin')->name('admin.')->group(function () {

    // Login door
    Route::get('login', [AdminLoginController::class, 'create'])->name('login');
    Route::post('login', [AdminLoginController::class, 'store'])->name('login.store');
    Route::post('logout', [AdminLoginController::class, 'destroy'])->middleware('auth')->name('logout');

    // Admin and owner
    Route::middleware(['auth', 'role:admin,owner'])->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('reports', [ReportController::class, 'index'])->name('reports');
    });

    // Admin only
    Route::middleware(['auth', 'role:admin'])->group(function () {
        // Online orders (pickup or courier shipping)
        Route::get('orders', [OrderController::class, 'index'])->name('orders');
        Route::get('orders/{transaction}', [OrderController::class, 'show'])->name('orders.show');
        Route::patch('orders/{transaction}/status', [OrderController::class, 'updateStatus'])->name('orders.status');
        Route::patch('orders/{transaction}/tracking-number', [OrderController::class, 'updateTrackingNumber'])->name('orders.tracking');

        Route::resource('categories', CategoryController::class)->except('show');
        Route::resource('products', AdminProductController::class)->except('show');
        Route::patch('products/{product}/stock', [AdminProductController::class, 'adjustStock'])->name('products.stock');
    });
});

// =====================================================================
// CASHIER AREA (POS): login at /cashier/login (cashier only)
// =====================================================================
Route::prefix('cashier')->name('cashier.')->group(function () {

    // Login door
    Route::get('login', [CashierLoginController::class, 'create'])->name('login');
    Route::post('login', [CashierLoginController::class, 'store'])->name('login.store');
    Route::post('logout', [CashierLoginController::class, 'destroy'])->middleware('auth')->name('logout');

    // POS terminal and receipts (in-store sales)
    Route::middleware(['auth', 'role:cashier'])->group(function () {
        Route::get('/', [PosController::class, 'index'])->name('pos');
        Route::get('history', [TransactionController::class, 'index'])->name('history');
        Route::get('receipt/{transaction}', [TransactionController::class, 'receipt'])->name('receipt');
    });
});

require __DIR__ . '/auth.php';
