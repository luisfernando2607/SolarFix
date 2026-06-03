<?php

use App\Http\Controllers\AccessoryController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceBrandController;
use App\Http\Controllers\DeviceModelController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('users', UserController::class);
    Route::resource('clients', ClientController::class);
    Route::resource('brands', DeviceBrandController::class);
    Route::resource('models', DeviceModelController::class);
    Route::resource('accessories', AccessoryController::class);
    Route::resource('orders', OrderController::class);

    Route::post('/orders/{order}/photos', [OrderController::class, 'uploadPhoto'])->name('orders.photo.upload');
    Route::delete('/orders/{order}/photos/{photo}', [OrderController::class, 'deletePhoto'])->name('orders.photo.delete');
});
