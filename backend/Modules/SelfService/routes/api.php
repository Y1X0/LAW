<?php

use Illuminate\Support\Facades\Route;
use Modules\SelfService\Http\Controllers\MyAttendanceController;
use Modules\SelfService\Http\Controllers\MyDashboardController;
use Modules\SelfService\Http\Controllers\MyLeaveController;
use Modules\SelfService\Http\Controllers\MyPayslipController;
use Modules\SelfService\Http\Controllers\MyProfileController;

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

    // حضوري (#50) — قراءة فقط
    Route::middleware('permission:attendance.view_own')
        ->get('attendance', [MyAttendanceController::class, 'index'])->name('me.attendance.index');

    // إجازاتي (#51)
    Route::middleware('permission:leave.view_own')->group(function () {
        Route::get('leave/balance', [MyLeaveController::class, 'balance'])->name('me.leave.balance');
        Route::get('leave/requests', [MyLeaveController::class, 'index'])->name('me.leave.requests');
    });
    // تقديم طلب لنفسي (صلاحية مستقلة عن العرض)
    Route::middleware('permission:leave.request_own')
        ->post('leave/requests', [MyLeaveController::class, 'store'])->name('me.leave.store');

    // ملفي (#52) — عرض/تعديل محدود
    Route::middleware('permission:profile.update_own')->group(function () {
        Route::get('profile', [MyProfileController::class, 'show'])->name('me.profile.show');
        Route::patch('profile', [MyProfileController::class, 'update'])->name('me.profile.update');
    });
});
