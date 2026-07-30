<?php

use Illuminate\Support\Facades\Route;
use Modules\Payroll\Http\Controllers\EmployeeSalaryProfileController;
use Modules\Payroll\Http\Controllers\PayrollPeriodController;
use Modules\Payroll\Http\Controllers\PayrollRunController;

/*
| مسارات وحدة Payroll (Epic 8 / Issue #32) — تُحمّل تلقائياً تحت /api.
| قراءة: payroll.view · إنشاء: payroll.create. لا حساب هنا (#36 لاحقاً).
*/

Route::middleware('auth.token')->group(function () {
    // قراءة
    Route::middleware('permission:payroll.view')->group(function () {
        Route::get('payroll-periods', [PayrollPeriodController::class, 'index'])->name('payroll-periods.index');
        Route::get('payroll-periods/{payroll_period}', [PayrollPeriodController::class, 'show'])->name('payroll-periods.show');
        Route::get('payroll-periods/{payroll_period}/runs', [PayrollRunController::class, 'index'])->name('payroll-runs.index');
        Route::get('payroll-runs/{payroll_run}', [PayrollRunController::class, 'show'])->name('payroll-runs.show');
        Route::get('employees/{employee}/salary-profiles', [EmployeeSalaryProfileController::class, 'index'])->name('salary-profiles.index');
    });

    // إنشاء
    Route::middleware('permission:payroll.create')->group(function () {
        Route::post('payroll-periods', [PayrollPeriodController::class, 'store'])->name('payroll-periods.store');
        Route::post('payroll-periods/{payroll_period}/runs', [PayrollRunController::class, 'store'])->name('payroll-runs.store');
        Route::post('employees/{employee}/salary-profiles', [EmployeeSalaryProfileController::class, 'store'])->name('salary-profiles.store');
    });
});
