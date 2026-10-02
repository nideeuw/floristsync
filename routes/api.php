<?php

use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\MidtransController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PosController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ShippingController;
use Illuminate\Support\Facades\Route;

// ---------- Public ----------
Route::get('products', [ProductController::class, 'index']);
Route::get('products/{product}', [ProductController::class, 'show']);

Route::middleware('throttle:30,1')->group(function () {
    Route::get('track/{orderNumber}', [OrderController::class, 'track']); // guest order tracking
    Route::get('shipping/destinations', [ShippingController::class, 'destinations']);
    Route::post('shipping/cost', [ShippingController::class, 'cost']);
});

// Midtrans webhook (no CSRF, no login)
Route::post('midtrans/notification', [MidtransController::class, 'notification']);

// ---------- Internal (session login + CSRF) ----------
Route::middleware(['web', 'auth'])->group(function () {

    // Any logged-in user
    Route::get('my/orders', [OrderController::class, 'mine']);
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::patch('notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'markRead']);

    // POS cashier
    Route::middleware('role:cashier')->prefix('pos')->group(function () {
        Route::get('products', [PosController::class, 'products']);
        Route::post('transactions', [PosController::class, 'store']);
    });

    // Orders
    Route::middleware('role:admin,cashier,owner')->group(function () {
        Route::get('orders', [OrderController::class, 'index']);
        Route::get('orders/{transaction}', [OrderController::class, 'show']);
    });
    Route::middleware('role:admin,cashier')->group(function () {
        Route::patch('orders/{transaction}/tracking-number', [OrderController::class, 'trackingNumber']);
        Route::patch('orders/{transaction}/status', [OrderController::class, 'status']);
    });

    // Dashboard chart data
    Route::middleware('role:admin,owner')->prefix('dashboard')->group(function () {
        Route::get('revenue', [DashboardController::class, 'revenue']);
        Route::get('top-categories', [DashboardController::class, 'topCategories']);
        Route::get('low-stock', [DashboardController::class, 'lowStock']);
    });
});
