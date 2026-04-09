<?php

use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\TimeSyncController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API Routes
|--------------------------------------------------------------------------
*/
Route::get('/time-sync', TimeSyncController::class);

// Menu search & browsing (public)
Route::get('/menu/search', [MenuController::class, 'search']);
Route::get('/menu/categories', [MenuController::class, 'categories']);
Route::get('/menu/{id}', [MenuController::class, 'show']);

// Cart (session-based, public, rate-limited)
Route::get('/cart', [CartController::class, 'show']);
Route::post('/cart/items', [CartController::class, 'addItem'])
    ->middleware(['rate-limit:registration']);
Route::patch('/cart/items/{cartItemId}', [CartController::class, 'updateItem']);
Route::delete('/cart/items/{cartItemId}', [CartController::class, 'removeItem']);
Route::delete('/cart', [CartController::class, 'clear']);

// Order creation (public - kiosk, rate-limited per checkout attempts)
Route::post('/orders', [OrderController::class, 'store'])
    ->middleware(['rate-limit:checkout']);
Route::get('/orders/{trackingToken}', [OrderController::class, 'show']);

/*
|--------------------------------------------------------------------------
| Authenticated API Routes (staff)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {
    // Full order detail (any authenticated staff)
    Route::get('/orders/{orderId}/detail', [OrderController::class, 'showDetail']);
    // Order transitions (any authenticated staff)
    Route::post('/orders/{orderId}/transition', [OrderController::class, 'transition']);
});

// Staff discount override (manager/admin with PIN for > $20)
Route::middleware(['auth', 'role:manager,administrator'])->group(function () {
    Route::post('/orders/{orderId}/discount', [OrderController::class, 'applyDiscount']);
});

// Payment routes (cashier, manager, administrator only — kitchen excluded)
Route::middleware(['auth', 'role:cashier,manager,administrator'])->group(function () {
    Route::post('/payments/intent', [PaymentController::class, 'createIntent']);
    Route::post('/payments/confirm', [PaymentController::class, 'confirm']);
});
