<?php

use Illuminate\Support\Facades\Route;
use Modules\Payroll\Http\Controllers\EmployeeSalaryComponentController;
use Modules\Payroll\Http\Controllers\EmployeeSalaryProfileController;
use Modules\Payroll\Http\Controllers\PayrollPeriodController;
use Modules\Payroll\Http\Controllers\PayrollRunController;
use Modules\Payroll\Http\Controllers\SalaryComponentController;

/*
| مسارات وحدة Payroll (Epic 8 / #32 الأساس + #33 المكوّنات) — تُحمّل تلقائياً تحت /api.
| قراءة: payroll.view · إنشاء/تعديل: payroll.create. لا حساب هنا (#36 لاحقاً).
*/

Route::middleware('auth.token')->group(function () {
    // قراءة
    Route::middleware('permission:payroll.view')->group(function () {
        Route::get('payroll-periods', [PayrollPeriodController::class, 'index'])->name('payroll-periods.index');
        Route::get('payroll-periods/{payroll_period}', [PayrollPeriodController::class, 'show'])->name('payroll-periods.show');
        Route::get('payroll-periods/{payroll_period}/runs', [PayrollRunController::class, 'index'])->name('payroll-runs.index');
        Route::get('payroll-runs/{payroll_run}', [PayrollRunController::class, 'show'])->name('payroll-runs.show');
        Route::get('employees/{employee}/salary-profiles', [EmployeeSalaryProfileController::class, 'index'])->name('salary-profiles.index');

        Route::get('salary-components', [SalaryComponentController::class, 'index'])->name('salary-components.index');
        Route::get('employees/{employee}/salary-components', [EmployeeSalaryComponentController::class, 'index'])->name('employee-salary-components.index');
    });

    // إنشاء/تعديل
    Route::middleware('permission:payroll.create')->group(function () {
        Route::post('payroll-periods', [PayrollPeriodController::class, 'store'])->name('payroll-periods.store');
        Route::post('payroll-periods/{payroll_period}/runs', [PayrollRunController::class, 'store'])->name('payroll-runs.store');
        Route::post('employees/{employee}/salary-profiles', [EmployeeSalaryProfileController::class, 'store'])->name('salary-profiles.store');

        Route::post('salary-components', [SalaryComponentController::class, 'store'])->name('salary-components.store');
        Route::put('salary-components/{salary_component}', [SalaryComponentController::class, 'update'])->name('salary-components.update');
        Route::post('employees/{employee}/salary-components', [EmployeeSalaryComponentController::class, 'store'])->name('employee-salary-components.store');
        Route::delete('employee-salary-components/{employee_salary_component}', [EmployeeSalaryComponentController::class, 'destroy'])->name('employee-salary-components.destroy');
    });
});
