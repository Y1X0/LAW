<?php

use Illuminate\Support\Facades\Route;
use Modules\Finance\Http\Controllers\FinanceCapabilitiesController;
use Modules\Finance\Http\Controllers\InvoiceController;
use Modules\Finance\Http\Controllers\PaymentController;

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
    // قدرات المستخدم المالية (للعرض في الواجهة فقط) — محروسة بالمصادقة، بلا صلاحية محدّدة.
    Route::get('finance/capabilities', [FinanceCapabilitiesController::class, 'show'])->name('finance.capabilities');

    Route::middleware('permission:invoices.view')->group(function () {
        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::get('invoices/{invoice}/payments', [PaymentController::class, 'index'])->name('invoices.payments.index');
    });

    // سندات القبض — تسجيل/عكس (لا حذف). Idempotency عبر ترويسة Idempotency-Key.
    Route::middleware('permission:payments.create')->group(function () {
        Route::post('invoices/{invoice}/payments', [PaymentController::class, 'store'])->name('invoices.payments.store');
        Route::post('payments/{payment}/reverse', [PaymentController::class, 'reverse'])->name('payments.reverse');
    });

    Route::middleware('permission:invoices.create')->group(function () {
        Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
        Route::put('invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
        Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
    });

    Route::middleware('permission:invoices.approve')
        ->post('invoices/{invoice}/approve', [InvoiceController::class, 'approve'])->name('invoices.approve');
});
