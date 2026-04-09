<?php

use App\Http\Controllers\Api\TimeSyncController;
use App\Livewire\Auth\LoginForm;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Guest / Kiosk Routes (no auth required)
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return view('pages.kiosk-home');
})->name('home');

Route::get('/menu', function () {
    return view('pages.kiosk-home');
})->name('menu');

Route::get('/cart', function () {
    return view('pages.cart');
})->name('cart');

Route::get('/order/{trackingToken}', function (string $trackingToken) {
    return view('pages.order-tracker', ['trackingToken' => $trackingToken]);
})->name('order.track');

Route::get('/checkout', function () {
    return view('pages.checkout');
})->name('checkout');

/*
|--------------------------------------------------------------------------
| Auth Routes
|--------------------------------------------------------------------------
*/
Route::get('/login', LoginForm::class)
    ->middleware(['rate-limit:login'])
    ->name('login');

Route::post('/logout', function () {
    auth()->logout();
    session()->invalidate();
    session()->regenerateToken();
    return redirect('/login');
})->name('logout');

Route::middleware(['auth'])->group(function () {
    Route::get('/password/change', \App\Livewire\Auth\ForcePasswordChange::class)
        ->name('password.force-change');
});

/*
|--------------------------------------------------------------------------
| Staff Routes (auth required)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->prefix('staff')->group(function () {
    Route::get('/orders', function () {
        return view('pages.staff-dashboard');
    })->name('staff.orders');
});

/*
|--------------------------------------------------------------------------
| Admin Routes (auth + administrator role required)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:administrator'])->prefix('admin')->group(function () {
    Route::get('/dashboard', function () {
        return view('pages.admin-dashboard');
    })->name('admin.dashboard');

    Route::get('/menu', function () {
        return view('pages.admin-menu');
    })->name('admin.menu');

    Route::get('/promotions', function () {
        return view('pages.admin-promotions');
    })->name('admin.promotions');

    Route::get('/users', function () {
        return view('pages.admin-users');
    })->name('admin.users');

    Route::get('/security', function () {
        return view('pages.admin-security');
    })->name('admin.security');

    Route::get('/security/audit', function () {
        return view('pages.admin-security-audit');
    })->name('admin.security.audit');

    Route::get('/alerts', function () {
        return view('pages.admin-dashboard');
    })->name('admin.alerts');
});

/*
|--------------------------------------------------------------------------
| Manager+ Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:manager,administrator'])->prefix('manager')->group(function () {
    Route::get('/reconciliation', function () {
        return view('pages.manager-reconciliation');
    })->name('manager.reconciliation');
});
