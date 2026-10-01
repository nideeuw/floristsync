<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ReportController;
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

// ---------- Customer (guest and member) ----------
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

// ---------- After login: send each role to its own page ----------
Route::get('/dashboard', function (Request $request) {
    return match ($request->user()->role?->role_name) {
        Role::ADMIN, Role::OWNER => redirect()->route('admin.dashboard'),
        Role::CASHIER => redirect()->route('cashier.pos'),
        default => redirect()->route('home'),
    };
})->middleware('auth')->name('dashboard');

// ---------- Breeze profile ----------
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// ---------- Admin and owner ----------
Route::middleware(['auth', 'role:admin,owner'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('reports', [ReportController::class, 'index'])->name('reports');
});

// ---------- Admin and cashier (order processing) ----------
Route::middleware(['auth', 'role:admin,cashier'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('orders', [OrderController::class, 'index'])->name('orders');
});

// ---------- Admin only ----------
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('categories', CategoryController::class)->except('show');
    Route::resource('products', AdminProductController::class);
});

// ---------- Cashier (POS) ----------
Route::middleware(['auth', 'role:cashier,admin'])->prefix('cashier')->name('cashier.')->group(function () {
    Route::get('/', [PosController::class, 'index'])->name('pos');
    Route::get('receipt/{transaction}', [TransactionController::class, 'receipt'])->name('receipt');
});

require __DIR__ . '/auth.php';
