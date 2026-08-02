<?php

use Illuminate\Support\Facades\Route;
use Modules\DataTransfer\Http\Controllers\DataExportController;

/*
| مسارات وحدة DataTransfer (استيراد/تصدير Excel) — تُحمّل تلقائياً تحت /api.
| التصدير (قراءة فقط) محميّ بصلاحية عرض الكيان — لا صلاحية جديدة. الاستيراد لاحقاً (مرحلة 2).
*/

Route::middleware('auth.token')->prefix('admin/data')->group(function () {
    Route::middleware('permission:employees.view')
        ->get('export/employees', [DataExportController::class, 'employees'])->name('data.export.employees');

    Route::middleware('permission:attendance.view')
        ->get('export/attendance', [DataExportController::class, 'attendance'])->name('data.export.attendance');

    Route::middleware('permission:leaves.view_all')
        ->get('export/leave-requests', [DataExportController::class, 'leaveRequests'])->name('data.export.leave-requests');

    Route::middleware('permission:payroll.view')
        ->get('export/payroll-items', [DataExportController::class, 'payrollItems'])->name('data.export.payroll-items');

    Route::middleware('permission:clients.view')
        ->get('export/clients', [DataExportController::class, 'clients'])->name('data.export.clients');

    Route::middleware('permission:cases.view_all')
        ->get('export/cases', [DataExportController::class, 'cases'])->name('data.export.cases');
});
