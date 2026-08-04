<?php

use Illuminate\Support\Facades\Route;
use Modules\Finance\Http\Controllers\InvoiceController;

/*
|--------------------------------------------------------------------------
| Finance module API routes
|--------------------------------------------------------------------------
|
| تُحمَّل تحت /api عبر ModuleServiceProvider. مغلّفة بـ auth.token ومحروسة بـ permission:.
| المدفوعات/المصروفات/التقارير تُضاف في PRات لاحقة.
|
*/

Route::middleware('auth.token')->group(function () {
    Route::middleware('permission:invoices.view')->group(function () {
        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    });

    Route::middleware('permission:invoices.create')->group(function () {
        Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
        Route::put('invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
        Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
    });

    Route::middleware('permission:invoices.approve')
        ->post('invoices/{invoice}/approve', [InvoiceController::class, 'approve'])->name('invoices.approve');
});
