<?php

use Illuminate\Support\Facades\Route;
use Modules\Attendance\Http\Controllers\AttendanceController;
use Modules\Attendance\Http\Controllers\BiometricDeviceController;
use Modules\Attendance\Http\Controllers\BiometricWebhookController;
use Modules\Attendance\Http\Controllers\EmployeeShiftController;
use Modules\Attendance\Http\Controllers\WorkShiftController;

/*
| مسارات وحدة Attendance (#15 الحضور + #16 البصمة) — تُحمّل تلقائياً تحت /api.
| محميّة بالمصادقة + صلاحيات attendance.*، عدا Webhook الجهاز (تحقق بالمفتاح السري).
*/

// استقبال Push/Webhook من جهاز البصمة — لا مصادقة مستخدم؛ التحقق بمفتاح الجهاز (#16).
Route::post('biometric/devices/{device}/webhook', BiometricWebhookController::class)
    ->name('biometric.webhook');

Route::middleware('auth.token')->group(function () {
    // قراءة
    Route::middleware('permission:attendance.view')->group(function () {
        Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::get('work-shifts', [WorkShiftController::class, 'index'])->name('work-shifts.index');
        Route::get('work-shifts/{work_shift}', [WorkShiftController::class, 'show'])->name('work-shifts.show');
        Route::get('employees/{employee}/shifts', [EmployeeShiftController::class, 'index'])->name('employees.shifts.index');
    });

    // تسجيل يدوي / إدارة (attendance.manual)
    Route::middleware('permission:attendance.manual')->group(function () {
        Route::post('attendance/check-in', [AttendanceController::class, 'checkIn'])->name('attendance.check-in');
        Route::post('attendance/check-out', [AttendanceController::class, 'checkOut'])->name('attendance.check-out');
        Route::post('attendance/manual', [AttendanceController::class, 'storeManual'])->name('attendance.manual');

        Route::post('work-shifts', [WorkShiftController::class, 'store'])->name('work-shifts.store');
        Route::put('work-shifts/{work_shift}', [WorkShiftController::class, 'update'])->name('work-shifts.update');
        Route::delete('work-shifts/{work_shift}', [WorkShiftController::class, 'destroy'])->name('work-shifts.destroy');

        Route::post('employees/{employee}/shifts', [EmployeeShiftController::class, 'store'])->name('employees.shifts.store');
    });

    // اعتماد (attendance.approve)
    Route::middleware('permission:attendance.approve')
        ->post('attendance/{record}/approve', [AttendanceController::class, 'approve'])->name('attendance.approve');

    // أجهزة البصمة ومزامنتها (#16) — attendance.devices
    Route::middleware('permission:attendance.devices')->group(function () {
        Route::get('biometric/devices', [BiometricDeviceController::class, 'index'])->name('biometric.devices.index');
        Route::post('biometric/devices', [BiometricDeviceController::class, 'store'])->name('biometric.devices.store');
        Route::get('biometric/devices/{device}', [BiometricDeviceController::class, 'show'])->name('biometric.devices.show');
        Route::put('biometric/devices/{device}', [BiometricDeviceController::class, 'update'])->name('biometric.devices.update');
        Route::delete('biometric/devices/{device}', [BiometricDeviceController::class, 'destroy'])->name('biometric.devices.destroy');
        Route::post('biometric/devices/{device}/sync', [BiometricDeviceController::class, 'sync'])->name('biometric.devices.sync');
    });
});
