<?php

use Illuminate\Support\Facades\Route;
use Modules\Legal\Http\Controllers\ArchiveController;
use Modules\Legal\Http\Controllers\CaseController;
use Modules\Legal\Http\Controllers\CasePartyController;
use Modules\Legal\Http\Controllers\ClientController;
use Modules\Legal\Http\Controllers\DocumentController;
use Modules\Legal\Http\Controllers\HearingController;
use Modules\Legal\Http\Controllers\LawyerDashboardController;
use Modules\Legal\Http\Controllers\TaskController;
use Modules\Legal\Http\Controllers\TimelineController;
use Modules\Legal\Http\Controllers\WorklogController;

/*
| مسارات وحدة Legal — تُحمّل تلقائياً تحت /api عبر ModuleServiceProvider.
| LC-1: العملاء · LC-2: القضايا + الإسناد (عزل view_own) · LC-3: الجلسات
| · LC-4: الخط الزمني (append-only) + المستندات · LC-5: المهام + الإنجاز اليومي.
*/
Route::middleware('auth.token')->group(function () {
    // ---- LC-1: العملاء ----
    Route::middleware('permission:clients.view')->group(function () {
        Route::get('clients', [ClientController::class, 'index'])->name('clients.index');
        Route::get('clients/{client}', [ClientController::class, 'show'])->name('clients.show');
    });

    Route::middleware('permission:clients.create')
        ->post('clients', [ClientController::class, 'store'])->name('clients.store');

    Route::middleware('permission:clients.update')->group(function () {
        Route::put('clients/{client}', [ClientController::class, 'update'])->name('clients.update');
        Route::patch('clients/{client}/status', [ClientController::class, 'setStatus'])->name('clients.status');
    });

    // ---- LC-2: القضايا ----
    // القراءة متاحة للإدارة (view_all) أو المحامي المسند (view_own) — والتنطيق داخل المتحكّم.
    Route::middleware('permission:cases.view_own,cases.view_all')->group(function () {
        Route::get('cases', [CaseController::class, 'index'])->name('cases.index');
        Route::get('cases/{case}', [CaseController::class, 'show'])->name('cases.show');
    });

    Route::middleware('permission:cases.create')
        ->post('cases', [CaseController::class, 'store'])->name('cases.store');

    Route::middleware('permission:cases.update')
        ->put('cases/{case}', [CaseController::class, 'update'])->name('cases.update');

    Route::middleware('permission:cases.assign')->group(function () {
        Route::post('cases/{case}/assign', [CaseController::class, 'assign'])->name('cases.assign');
        Route::delete('cases/{case}/assign/{employee}', [CaseController::class, 'unassign'])->name('cases.unassign');
    });

    Route::middleware('permission:cases.close')
        ->post('cases/{case}/close', [CaseController::class, 'close'])->name('cases.close');

    // ---- LG-2: أطراف القضية ----
    // القراءة ترث عزل القضية؛ الإضافة تحت cases.update.
    Route::middleware('permission:cases.view_own,cases.view_all')
        ->get('cases/{case}/parties', [CasePartyController::class, 'index'])->name('cases.parties.index');
    Route::middleware('permission:cases.update')
        ->post('cases/{case}/parties', [CasePartyController::class, 'store'])->name('cases.parties.store');

    // ---- LC-3: الجلسات ----
    // القراءة ترث عزل القضية (view_own/view_all) — والتنطيق/الحارس داخل المتحكّم.
    Route::middleware('permission:cases.view_own,cases.view_all')->group(function () {
        Route::get('hearings', [HearingController::class, 'index'])->name('hearings.index');
        Route::get('cases/{case}/hearings', [HearingController::class, 'caseIndex'])->name('cases.hearings.index');
        Route::get('hearings/{hearing}', [HearingController::class, 'show'])->name('hearings.show');
    });

    Route::middleware('permission:hearings.manage')->group(function () {
        Route::post('cases/{case}/hearings', [HearingController::class, 'store'])->name('hearings.store');
        Route::put('hearings/{hearing}', [HearingController::class, 'update'])->name('hearings.update');
        Route::post('hearings/{hearing}/postpone', [HearingController::class, 'postpone'])->name('hearings.postpone');
        Route::post('hearings/{hearing}/cancel', [HearingController::class, 'cancel'])->name('hearings.cancel');
    });

    // ---- LC-4: الخط الزمني (Append-Only) + المستندات (Metadata) ----
    // القراءة ترث عزل القضية (view_own/view_all) — والحارس داخل المتحكّم.
    Route::middleware('permission:cases.view_own,cases.view_all')->group(function () {
        Route::get('cases/{case}/timeline', [TimelineController::class, 'index'])->name('cases.timeline.index');
        Route::get('cases/{case}/documents', [DocumentController::class, 'index'])->name('cases.documents.index');
        // تنزيل الملف يرث نفس حارس رؤية القضية (لا صلاحية جديدة) — Phase 5 PR-2.
        Route::get('documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    });

    // الخط الزمني: إضافة فقط (لا PUT/DELETE) — تحت cases.update.
    Route::middleware('permission:cases.update')
        ->post('cases/{case}/timeline', [TimelineController::class, 'store'])->name('cases.timeline.store');

    // المستندات: إضافة (documents.upload) وحذف (documents.delete).
    Route::middleware('permission:documents.upload')
        ->post('cases/{case}/documents', [DocumentController::class, 'store'])->name('cases.documents.store');
    Route::middleware('permission:documents.delete')
        ->delete('documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

    // ---- LC-5: المهام (عزل بالإسناد) ----
    Route::middleware('permission:tasks.view_own,tasks.view_all')->group(function () {
        Route::get('tasks', [TaskController::class, 'index'])->name('tasks.index');
        Route::get('tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
    });
    Route::middleware('permission:tasks.create')->group(function () {
        Route::post('tasks', [TaskController::class, 'store'])->name('tasks.store');
        Route::put('tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    });
    Route::middleware('permission:tasks.assign')
        ->patch('tasks/{task}/assign', [TaskController::class, 'assign'])->name('tasks.assign');
    Route::middleware('permission:tasks.complete')
        ->patch('tasks/{task}/complete', [TaskController::class, 'complete'])->name('tasks.complete');

    // ---- LC-5: الإنجاز اليومي ----
    // ذاتي: يتطلّب ربطاً بموظف (employee.linked).
    Route::middleware('employee.linked')->group(function () {
        Route::middleware('permission:worklog.view_own')
            ->get('me/worklog', [WorklogController::class, 'mine'])->name('me.worklog.index');
        Route::middleware('permission:worklog.submit_own')
            ->post('me/worklog', [WorklogController::class, 'submit'])->name('me.worklog.submit');
    });
    // اطّلاع الإدارة على كل السجلات.
    Route::middleware('permission:worklog.view_all')
        ->get('worklog', [WorklogController::class, 'index'])->name('worklog.index');

    // ---- LG-1: لوحة المحامي (تجميع ذاتي) ----
    Route::middleware(['employee.linked', 'permission:cases.view_own'])
        ->get('me/legal-summary', [LawyerDashboardController::class, 'summary'])->name('me.legal-summary');

    // ---- LG-3: فهرسة الأرشيف الورقي (عدّة مواقع لكل قضية) ----
    // صلاحيات أرشيف مستقلة + العزل يرث القضية.
    Route::middleware('permission:archive.view')
        ->get('cases/{case}/archive-locations', [ArchiveController::class, 'index'])->name('cases.archive.index');
    Route::middleware('permission:archive.create')
        ->post('cases/{case}/archive-locations', [ArchiveController::class, 'store'])->name('cases.archive.store');
    Route::middleware('permission:archive.update')
        ->put('archive-locations/{location}', [ArchiveController::class, 'update'])->name('archive.update');
    Route::middleware('permission:archive.delete')
        ->delete('archive-locations/{location}', [ArchiveController::class, 'destroy'])->name('archive.destroy');
});
