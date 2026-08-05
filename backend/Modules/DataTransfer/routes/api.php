<?php

use Illuminate\Support\Facades\Route;
use Modules\DataTransfer\Http\Controllers\DataExportController;
use Modules\DataTransfer\Http\Controllers\DataImportController;

/*
| مسارات وحدة DataTransfer (استيراد/تصدير Excel) — تُحمّل تلقائياً تحت /api.
| التصدير (قراءة فقط) محميّ بصلاحية عرض الكيان. الاستيراد محميّ بصلاحية إنشاء الكيان.
| لا صلاحية/هجرة جديدة.
*/

Route::middleware('auth.token')->prefix('admin/data')->group(function () {
    // سِجلّ المستوردات المتاحة للمستخدم (مصفّى بصلاحيّاته) — تبني منه الواجهة قائمة الأنواع.
    Route::get('import/manifest', [DataImportController::class, 'manifest'])->name('data.import.manifest');

    // الاستيراد: معاينة بلا حفظ، ثم حفظ ذرّي — محميّ بصلاحية إنشاء الكيان.
    Route::middleware('permission:employees.create')->group(function () {
        Route::post('import/employees/preview', [DataImportController::class, 'previewEmployees'])->name('data.import.employees.preview');
        Route::post('import/employees/commit', [DataImportController::class, 'commitEmployees'])->name('data.import.employees.commit');
    });

    // العملاء: يمرّ عبر ClientService (لا يتجاوز منطق النطاق).
    Route::middleware('permission:clients.create')->group(function () {
        Route::post('import/clients/preview', [DataImportController::class, 'previewClients'])->name('data.import.clients.preview');
        Route::post('import/clients/commit', [DataImportController::class, 'commitClients'])->name('data.import.clients.commit');
    });

    // القضايا: تمرّ عبر CaseService (تدقيق + إسناد lead + خط زمني استيراد صادق لكل صفّ).
    Route::middleware('permission:cases.create')->group(function () {
        Route::post('import/cases/preview', [DataImportController::class, 'previewCases'])->name('data.import.cases.preview');
        Route::post('import/cases/commit', [DataImportController::class, 'commitCases'])->name('data.import.cases.commit');
    });

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
