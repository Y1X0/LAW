<?php

use Illuminate\Support\Facades\Route;
use Modules\Payroll\Http\Controllers\EmployeeSalaryComponentController;
use Modules\Payroll\Http\Controllers\EmployeeSalaryProfileController;
use Modules\Payroll\Http\Controllers\PayrollApprovalController;
use Modules\Payroll\Http\Controllers\PayrollAttendanceController;
use Modules\Payroll\Http\Controllers\PayrollCalculationController;
use Modules\Payroll\Http\Controllers\PayrollLeaveController;
use Modules\Payroll\Http\Controllers\PayrollPeriodController;
use Modules\Payroll\Http\Controllers\PayrollReportController;
use Modules\Payroll\Http\Controllers\PayrollRunController;
use Modules\Payroll\Http\Controllers\PayslipController;
use Modules\Payroll\Http\Controllers\SalaryComponentController;

/*
| مسارات وحدة Payroll (Epic 8 / #32 الأساس + #33 المكوّنات + #34 تكامل الحضور).
| قراءة: payroll.view · إنشاء/تعديل: payroll.create. لا حساب نهائي هنا (#36 لاحقاً).
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

        // تكامل الحضور (#34) — قراءة/معاينة
        Route::get('employees/{employee}/attendance-summary', [PayrollAttendanceController::class, 'preview'])->name('payroll-attendance.preview');
        Route::get('payroll-runs/{payroll_run}/attendance-summaries', [PayrollAttendanceController::class, 'index'])->name('payroll-attendance.index');

        // تكامل الإجازات (#35) — قراءة/معاينة
        Route::get('employees/{employee}/leave-summary', [PayrollLeaveController::class, 'preview'])->name('payroll-leave.preview');
        Route::get('payroll-runs/{payroll_run}/leave-summaries', [PayrollLeaveController::class, 'index'])->name('payroll-leave.index');

        // محرّك الحساب (#36) — قراءة النتائج
        Route::get('payroll-runs/{payroll_run}/items', [PayrollCalculationController::class, 'index'])->name('payroll-items.index');
        Route::get('payroll-items/{payroll_item}', [PayrollCalculationController::class, 'show'])->name('payroll-items.show');

        // كشوف الرواتب (#37) — عرض/تصدير
        Route::get('payroll-runs/{payroll_run}/payslips', [PayslipController::class, 'index'])->name('payslips.index');
        Route::get('payroll-items/{payroll_item}/payslip', [PayslipController::class, 'show'])->name('payslips.show');
        Route::get('payroll-items/{payroll_item}/payslip/html', [PayslipController::class, 'html'])->name('payslips.html');

        // التقارير (#38) — من النتائج المجمّدة فقط
        Route::get('payroll-reports/cost', [PayrollReportController::class, 'cost'])->name('payroll-reports.cost');
        Route::get('payroll-reports/employees/{employee}', [PayrollReportController::class, 'employee'])->name('payroll-reports.employee');
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

        // تكامل الحضور (#34) — بناء اللقطة
        Route::post('payroll-runs/{payroll_run}/attendance-snapshot', [PayrollAttendanceController::class, 'snapshot'])->name('payroll-attendance.snapshot');

        // تكامل الإجازات (#35) — بناء اللقطة
        Route::post('payroll-runs/{payroll_run}/leave-snapshot', [PayrollLeaveController::class, 'snapshot'])->name('payroll-leave.snapshot');

        // محرّك الحساب (#36) — تشغيل الحساب
        Route::post('payroll-runs/{payroll_run}/calculate', [PayrollCalculationController::class, 'calculate'])->name('payroll-items.calculate');
    });

    // اعتماد المسير (#37)
    Route::middleware('permission:payroll.approve')
        ->post('payroll-runs/{payroll_run}/approve', [PayrollApprovalController::class, 'approve'])->name('payroll-runs.approve');

    // قفل المسير (#37)
    Route::middleware('permission:payroll.pay')
        ->post('payroll-runs/{payroll_run}/lock', [PayrollApprovalController::class, 'lock'])->name('payroll-runs.lock');
});
