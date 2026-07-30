<?php

use Illuminate\Support\Facades\Route;
use Modules\Leave\Http\Controllers\LeaveBalanceController;
use Modules\Leave\Http\Controllers\LeaveRequestController;
use Modules\Leave\Http\Controllers\LeaveTypeController;

/*
| مسارات وحدة Leave (Issue #17) — تُحمّل تلقائياً تحت /api.
| leaves.request: تقديم/قراءة الأنواع · leaves.view_all: عرض الطلبات/الأرصدة
| leaves.approve: اعتماد/رفض/إلغاء + إدارة الأنواع والأرصدة.
*/

Route::middleware('auth.token')->group(function () {
    // قراءة الأنواع (متاحة لكل من يقدّم طلباً) + تقديم الطلب
    Route::middleware('permission:leaves.request')->group(function () {
        Route::get('leave-types', [LeaveTypeController::class, 'index'])->name('leave-types.index');
        Route::post('leave-requests', [LeaveRequestController::class, 'store'])->name('leave-requests.store');
    });

    // عرض الطلبات والأرصدة
    Route::middleware('permission:leaves.view_all')->group(function () {
        Route::get('leave-requests', [LeaveRequestController::class, 'index'])->name('leave-requests.index');
        Route::get('employees/{employee}/leave-balances', [LeaveBalanceController::class, 'index'])->name('leave-balances.index');
    });

    // اعتماد/رفض/إلغاء + إدارة الأنواع والأرصدة
    Route::middleware('permission:leaves.approve')->group(function () {
        Route::post('leave-requests/{leave_request}/approve', [LeaveRequestController::class, 'approve'])->name('leave-requests.approve');
        Route::post('leave-requests/{leave_request}/reject', [LeaveRequestController::class, 'reject'])->name('leave-requests.reject');
        Route::post('leave-requests/{leave_request}/cancel', [LeaveRequestController::class, 'cancel'])->name('leave-requests.cancel');

        Route::post('leave-types', [LeaveTypeController::class, 'store'])->name('leave-types.store');
        Route::put('leave-types/{leave_type}', [LeaveTypeController::class, 'update'])->name('leave-types.update');

        Route::post('employees/{employee}/leave-balances', [LeaveBalanceController::class, 'store'])->name('leave-balances.store');
    });
});
