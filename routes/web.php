<?php

use App\Http\Controllers\AccessoryController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CashClosingController;
use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientMergeController;
use App\Http\Controllers\CommissionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceBrandController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MaintenancePlanController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\DeviceModelController;
use App\Http\Controllers\OrderChecklistController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderPartController;
use App\Http\Controllers\OrderWorkController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SignatureController;
use App\Http\Controllers\SparePartController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WarrantyController;
use App\Http\Controllers\WhatsAppController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

// Seguimiento público de la orden (enlace privado por orden; límite contra fuerza bruta).
Route::get('/seguimiento/{token}', [TrackingController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{32}')
    ->middleware('throttle:30,1')
    ->name('tracking.show');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/search', [SearchController::class, 'index'])->name('search');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/{id}', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    Route::middleware('role:super_admin,admin')->group(function () {
        Route::resource('users', UserController::class);
        Route::resource('brands', DeviceBrandController::class);
        Route::resource('models', DeviceModelController::class);
        Route::resource('accessories', AccessoryController::class);
        Route::get('/audit', [AuditLogController::class, 'index'])->name('audit.index');
        Route::resource('suppliers', SupplierController::class)->except(['destroy']);
        Route::get('/spare-parts/create', [SparePartController::class, 'create'])->name('spare-parts.create');
        Route::post('/spare-parts', [SparePartController::class, 'store'])->name('spare-parts.store');
        Route::get('/spare-parts/{spare_part}/edit', [SparePartController::class, 'edit'])->name('spare-parts.edit');
        Route::put('/spare-parts/{spare_part}', [SparePartController::class, 'update'])->name('spare-parts.update');
        Route::delete('/spare-parts/{spare_part}', [SparePartController::class, 'destroy'])->name('spare-parts.destroy');
        Route::post('/spare-parts/{spare_part}/adjust', [SparePartController::class, 'adjust'])->name('spare-parts.adjust');
        Route::resource('checklists', ChecklistController::class)->except(['show', 'destroy']);
        Route::resource('branches', BranchController::class)->except(['show', 'destroy']);
        Route::post('/branch-context', [BranchController::class, 'switch'])->name('branch-context');
        Route::post('/commissions/pay', [CommissionController::class, 'pay'])->name('commissions.pay');
        Route::get('/clients-duplicates', [ClientMergeController::class, 'duplicates'])->name('clients.duplicates');
        Route::post('/clients/{client}/merge', [ClientMergeController::class, 'merge'])->name('clients.merge');
        Route::post('/clients-merge-group', [ClientMergeController::class, 'mergeGroup'])->name('clients.merge-group');
        Route::post('/commissions/advances', [CommissionController::class, 'storeAdvance'])->name('commissions.advances.store');
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export/{dataset}', [ReportController::class, 'export'])->name('reports.export');
        Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
    });

    Route::middleware('role:super_admin,admin,receptionist')->group(function () {
        Route::resource('clients', ClientController::class);
    });

    Route::middleware('role:super_admin,admin,technician,receptionist')->group(function () {
        Route::get('/clients-search', [ClientController::class, 'search'])->name('clients.search');
        Route::get('/commissions', [CommissionController::class, 'index'])->name('commissions.index');
        Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments.index');
        Route::get('/appointments/{appointment}', [AppointmentController::class, 'show'])->name('appointments.show')->whereNumber('appointment');
        Route::patch('/appointments/{appointment}/status', [AppointmentController::class, 'status'])->name('appointments.status');
        Route::post('/appointments/{appointment}/whatsapp/{type}', [AppointmentController::class, 'whatsapp'])->name('appointments.whatsapp');
        Route::get('/spare-parts', [SparePartController::class, 'index'])->name('spare-parts.index');
        Route::get('/spare-parts-search', [SparePartController::class, 'search'])->name('spare-parts.search');
        Route::get('/spare-parts/{spare_part}', [SparePartController::class, 'show'])->name('spare-parts.show')->whereNumber('spare_part');
        Route::post('/spare-parts/{spare_part}/receive', [SparePartController::class, 'receive'])
            ->name('spare-parts.receive')->middleware('role:super_admin,admin,technician');
        Route::post('/spare-parts/{spare_part}/transfer', [SparePartController::class, 'transfer'])
            ->name('spare-parts.transfer')->middleware('role:super_admin,admin,technician');
        Route::post('/orders/{order}/parts', [OrderPartController::class, 'store'])->name('orders.parts.store');
        Route::patch('/orders/{order}/quick-status', [OrderWorkController::class, 'status'])->name('orders.quick-status');
        Route::post('/orders/{order}/take', [OrderWorkController::class, 'take'])->name('orders.take');
        Route::patch('/orders/{order}/work', [OrderWorkController::class, 'work'])->name('orders.work');
        Route::post('/orders/{order}/notes', [OrderWorkController::class, 'addNote'])->name('orders.notes.store');
        Route::delete('/orders/{order}/notes/{note}', [OrderWorkController::class, 'deleteNote'])->name('orders.notes.destroy');
        Route::controller(OrderChecklistController::class)->where(['stage' => 'reception|diagnosis|delivery'])->group(function () {
            Route::get('/orders/{order}/checklists/{stage}', 'edit')->name('orders.checklists.edit');
            Route::put('/orders/{order}/checklists/{stage}', 'update')->name('orders.checklists.update');
        });
        Route::delete('/orders/{order}/parts/{part}', [OrderPartController::class, 'destroy'])->name('orders.parts.destroy');
        Route::resource('orders', OrderController::class)->except(['destroy']);
        Route::get('/orders/{order}/pdf', [OrderController::class, 'pdf'])->name('orders.pdf');
        Route::get('/orders/{order}/ticket', [OrderController::class, 'ticket'])->name('orders.ticket');
        Route::post('/orders/{order}/photos', [OrderController::class, 'uploadPhoto'])->name('orders.photo.upload');
        Route::get('/orders/{order}/photos/{photo}', [OrderController::class, 'showPhoto'])->name('orders.photo.show');
        Route::delete('/orders/{order}/photos/{photo}', [OrderController::class, 'deletePhoto'])->name('orders.photo.delete');
        Route::post('/orders/{order}/whatsapp/{type}', [WhatsAppController::class, 'send'])->name('orders.whatsapp');
        Route::controller(SignatureController::class)->where(['stage' => 'reception|delivery'])->group(function () {
            Route::get('/orders/{order}/signatures/{stage}', 'create')->name('orders.signatures.create');
            Route::post('/orders/{order}/signatures/{stage}', 'store')->name('orders.signatures.store');
            Route::get('/orders/{order}/signatures/{stage}/image', 'image')->name('orders.signatures.image');
        });

        // La recepción cobra (flujo 4); eliminar pagos queda solo para administradores.
        Route::middleware('role:super_admin,admin,receptionist')->group(function () {
            Route::post('/orders/{order}/payments', [PaymentController::class, 'store'])->name('orders.payments.store');
            Route::get('/orders/{order}/payments/{payment}/receipt', [PaymentController::class, 'receiptPdf'])->name('orders.payments.receipt');
        });
        Route::middleware('role:super_admin,admin')->group(function () {
            Route::delete('/orders/{order}/payments/{payment}', [PaymentController::class, 'destroy'])->name('orders.payments.destroy');
        });

        Route::middleware('role:super_admin,admin,receptionist')->group(function () {
            Route::get('/invoices/payments/list', [InvoiceController::class, 'payments'])->name('invoices.payments');
            Route::resource('invoices', InvoiceController::class)->only(['index', 'create', 'store', 'show']);
            Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
            Route::post('/orders/{order}/invoice', [InvoiceController::class, 'generateFromOrder'])->name('invoices.generate-from-order');
            Route::get('/cash', [CashClosingController::class, 'index'])->name('cash.index');
            Route::resource('maintenance-plans', MaintenancePlanController::class)->except(['show', 'destroy']);
            Route::post('/maintenance-plans/{maintenance_plan}/whatsapp', [MaintenancePlanController::class, 'whatsapp'])->name('maintenance-plans.whatsapp');
            Route::get('/appointments/create', [AppointmentController::class, 'create'])->name('appointments.create');
            Route::post('/appointments', [AppointmentController::class, 'store'])->name('appointments.store');
            Route::get('/appointments/{appointment}/edit', [AppointmentController::class, 'edit'])->name('appointments.edit');
            Route::put('/appointments/{appointment}', [AppointmentController::class, 'update'])->name('appointments.update');
            Route::get('/warranties', [WarrantyController::class, 'index'])->name('warranties.index');
            Route::patch('/warranties/{warranty}', [WarrantyController::class, 'update'])->name('warranties.update');
            Route::get('/orders/{order}/warranty-claim', [WarrantyController::class, 'createClaim'])->name('warranties.claim.create');
            Route::post('/orders/{order}/warranty-claim', [WarrantyController::class, 'storeClaim'])->name('warranties.claim.store');
            Route::post('/cash', [CashClosingController::class, 'store'])->name('cash.store');
            Route::post('/cash/movements', [CashClosingController::class, 'storeMovement'])->name('cash.movements.store');
            Route::delete('/cash/movements/{movement}', [CashClosingController::class, 'destroyMovement'])->name('cash.movements.destroy');
        });
        Route::middleware('role:super_admin,admin')->group(function () {
            Route::delete('/orders/{order}', [OrderController::class, 'destroy'])->name('orders.destroy');
            Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
            Route::patch('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
            Route::patch('/invoices/{invoice}/mark-paid', [InvoiceController::class, 'markPaid'])->name('invoices.mark-paid');
        });
    });
});
