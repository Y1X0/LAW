<?php

use Illuminate\Support\Facades\Route;
use Modules\Legal\Http\Controllers\CaseController;
use Modules\Legal\Http\Controllers\ClientController;
use Modules\Legal\Http\Controllers\DocumentController;
use Modules\Legal\Http\Controllers\HearingController;
use Modules\Legal\Http\Controllers\TimelineController;

/*
| مسارات وحدة Legal — تُحمّل تلقائياً تحت /api عبر ModuleServiceProvider.
| LC-1: العملاء (clients.*) · LC-2: القضايا + الإسناد (cases.*) بعزل view_own
| · LC-3: الجلسات (hearings) · LC-4: الخط الزمني (append-only) + المستندات (metadata).
| القراءة في LC-3/LC-4 ترث عزل القضية؛ الكتابة بصلاحياتها.
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
    });

    // الخط الزمني: إضافة فقط (لا PUT/DELETE) — تحت cases.update.
    Route::middleware('permission:cases.update')
        ->post('cases/{case}/timeline', [TimelineController::class, 'store'])->name('cases.timeline.store');

    // المستندات: إضافة (documents.upload) وحذف (documents.delete).
    Route::middleware('permission:documents.upload')
        ->post('cases/{case}/documents', [DocumentController::class, 'store'])->name('cases.documents.store');
    Route::middleware('permission:documents.delete')
        ->delete('documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
});
