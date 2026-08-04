<?php

use Illuminate\Support\Facades\Route;
use Modules\Finance\Http\Controllers\ClientFinanceSummaryController;
use Modules\Finance\Http\Controllers\ExpenseCategoryController;
use Modules\Finance\Http\Controllers\ExpenseController;
use Modules\Finance\Http\Controllers\FinanceCapabilitiesController;
use Modules\Finance\Http\Controllers\FinanceReportController;
use Modules\Finance\Http\Controllers\FinancialAccountController;
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
        Route::get('finance/clients/{client}/summary', [ClientFinanceSummaryController::class, 'show'])->name('finance.clients.summary');
    });

    // الحسابات النقدية — يحتاجها مسجّلو القبض والصرف معاً (اختيار الحساب في النماذج).
    Route::middleware('permission:payments.create,expenses.create')
        ->get('finance/accounts', [FinancialAccountController::class, 'index'])->name('finance.accounts.index');

    // سندات القبض — تسجيل/عكس (لا حذف). Idempotency عبر ترويسة Idempotency-Key.
    Route::middleware('permission:payments.create')->group(function () {
        Route::post('invoices/{invoice}/payments', [PaymentController::class, 'store'])->name('invoices.payments.store');
        Route::post('payments/{payment}/reverse', [PaymentController::class, 'reverse'])->name('payments.reverse');
    });

    // سندات الصرف — قراءة/تسجيل/عكس (لا حذف). عزل القضية عند الربط.
    Route::middleware('permission:expenses.create')->group(function () {
        Route::get('finance/expense-categories', [ExpenseCategoryController::class, 'index'])->name('finance.expense-categories.index');
        Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::post('expenses/{expense}/reverse', [ExpenseController::class, 'reverse'])->name('expenses.reverse');
    });

    Route::middleware('permission:invoices.create')->group(function () {
        Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
        Route::put('invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
        Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
    });

    Route::middleware('permission:invoices.approve')
        ->post('invoices/{invoice}/approve', [InvoiceController::class, 'approve'])->name('invoices.approve');

    // التقارير المالية — قراءة فقط.
    Route::middleware('permission:finance.reports')->group(function () {
        Route::get('finance/reports/trial-balance', [FinanceReportController::class, 'trialBalance'])->name('finance.reports.trial-balance');
        Route::get('finance/reports/receivables', [FinanceReportController::class, 'receivables'])->name('finance.reports.receivables');
        Route::get('finance/reports/income-expense', [FinanceReportController::class, 'incomeExpense'])->name('finance.reports.income-expense');
    });
});
