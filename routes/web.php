<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', [StatsController::class, 'overview'])->name('overview');
    Route::get('/destinations', [StatsController::class, 'destinations'])->name('destinations');
    Route::get('/time', [StatsController::class, 'time'])->name('time');
    Route::get('/cancellations', [StatsController::class, 'cancellations'])->name('cancellations');
    Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers');
    Route::get('/suppliers/setup', [SupplierController::class, 'setup'])->name('suppliers.setup');
    Route::post('/suppliers/setup', [SupplierController::class, 'saveSetup']);
    Route::post('/suppliers/{supplier}/payments', [SupplierController::class, 'storePayment'])->whereNumber('supplier')->name('suppliers.payments.store');
    Route::post('/suppliers/balance-from', [SupplierController::class, 'saveBalanceFrom'])->name('suppliers.balance-from');
    Route::delete('/suppliers/payments/{payment}', [SupplierController::class, 'destroyPayment'])->name('suppliers.payments.destroy');
});
