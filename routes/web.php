<?php

use App\Http\Controllers\AccessoryController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CashClosingController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceBrandController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\DeviceModelController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SignatureController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WarrantyController;
use App\Http\Controllers\WhatsAppController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::middleware('role:super_admin,admin')->group(function () {
        Route::resource('users', UserController::class);
        Route::resource('brands', DeviceBrandController::class);
        Route::resource('models', DeviceModelController::class);
        Route::resource('accessories', AccessoryController::class);
        Route::get('/audit', [AuditLogController::class, 'index'])->name('audit.index');
        Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
    });

    Route::middleware('role:super_admin,admin,receptionist')->group(function () {
        Route::resource('clients', ClientController::class);
    });

    Route::middleware('role:super_admin,admin,technician,receptionist')->group(function () {
        Route::get('/clients-search', [ClientController::class, 'search'])->name('clients.search');
        Route::resource('orders', OrderController::class)->except(['destroy']);
        Route::get('/orders/{order}/pdf', [OrderController::class, 'pdf'])->name('orders.pdf');
        Route::post('/orders/{order}/photos', [OrderController::class, 'uploadPhoto'])->name('orders.photo.upload');
        Route::get('/orders/{order}/photos/{photo}', [OrderController::class, 'showPhoto'])->name('orders.photo.show');
        Route::delete('/orders/{order}/photos/{photo}', [OrderController::class, 'deletePhoto'])->name('orders.photo.delete');
        Route::post('/orders/{order}/whatsapp/{type}', [WhatsAppController::class, 'send'])->name('orders.whatsapp');
        Route::controller(SignatureController::class)->where(['stage' => 'reception|delivery'])->group(function () {
            Route::get('/orders/{order}/signatures/{stage}', 'create')->name('orders.signatures.create');
            Route::post('/orders/{order}/signatures/{stage}', 'store')->name('orders.signatures.store');
            Route::get('/orders/{order}/signatures/{stage}/image', 'image')->name('orders.signatures.image');
        });

        Route::middleware('role:super_admin,admin')->group(function () {
            Route::post('/orders/{order}/payments', [PaymentController::class, 'store'])->name('orders.payments.store');
            Route::get('/orders/{order}/payments/{payment}/receipt', [PaymentController::class, 'receiptPdf'])->name('orders.payments.receipt');
            Route::delete('/orders/{order}/payments/{payment}', [PaymentController::class, 'destroy'])->name('orders.payments.destroy');
        });

        Route::middleware('role:super_admin,admin,receptionist')->group(function () {
            Route::get('/invoices/payments/list', [InvoiceController::class, 'payments'])->name('invoices.payments');
            Route::resource('invoices', InvoiceController::class)->only(['index', 'create', 'store', 'show']);
            Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
            Route::post('/orders/{order}/invoice', [InvoiceController::class, 'generateFromOrder'])->name('invoices.generate-from-order');
            Route::get('/cash', [CashClosingController::class, 'index'])->name('cash.index');
            Route::get('/warranties', [WarrantyController::class, 'index'])->name('warranties.index');
            Route::patch('/warranties/{warranty}', [WarrantyController::class, 'update'])->name('warranties.update');
            Route::get('/orders/{order}/warranty-claim', [WarrantyController::class, 'createClaim'])->name('warranties.claim.create');
            Route::post('/orders/{order}/warranty-claim', [WarrantyController::class, 'storeClaim'])->name('warranties.claim.store');
            Route::post('/cash', [CashClosingController::class, 'store'])->name('cash.store');
        });
        Route::middleware('role:super_admin,admin')->group(function () {
            Route::delete('/orders/{order}', [OrderController::class, 'destroy'])->name('orders.destroy');
            Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
            Route::patch('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
            Route::patch('/invoices/{invoice}/mark-paid', [InvoiceController::class, 'markPaid'])->name('invoices.mark-paid');
        });
    });
});
