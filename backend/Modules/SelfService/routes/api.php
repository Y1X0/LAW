<?php

use Illuminate\Support\Facades\Route;
use Modules\SelfService\Http\Controllers\MyDashboardController;
use Modules\SelfService\Http\Controllers\MyPayslipController;

/*
| مسارات وحدة SelfService (Epic 9) — سطح الخدمة الذاتية للموظف تحت /api/me.
| كل المسارات محميّة بـ: auth.token + employee.linked (موظف مرتبط) + صلاحية *_own.
| الوصول دائماً من Auth::user()->employee — لا وصول لبيانات موظف آخر.
*/

Route::middleware(['auth.token', 'employee.linked'])->prefix('me')->group(function () {
    // لوحة شخصية (#48)
    Route::middleware('permission:dashboard.view_own')
        ->get('dashboard', [MyDashboardController::class, 'show'])->name('me.dashboard');

    // كشوفي (#49)
    Route::middleware('permission:payslip.view_own')->group(function () {
        Route::get('payslips', [MyPayslipController::class, 'index'])->name('me.payslips.index');
        Route::get('payslips/{payroll_item}', [MyPayslipController::class, 'show'])->name('me.payslips.show');
        Route::get('payslips/{payroll_item}/html', [MyPayslipController::class, 'html'])->name('me.payslips.html');
    });
});
