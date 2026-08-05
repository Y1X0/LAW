<?php

use Illuminate\Support\Facades\Route;
use Modules\CustomFields\Http\Controllers\CustomFieldController;

/*
| مسارات وحدة CustomFields (Phase 12) — تُحمّل تلقائياً تحت /api.
| إدارة تعريفات الحقول المخصّصة محميّة بصلاحية custom_fields.manage (المدير افتراضياً).
*/

Route::middleware(['auth.token', 'permission:custom_fields.manage'])
    ->prefix('admin/custom-fields')
    ->group(function () {
        Route::get('/', [CustomFieldController::class, 'index'])->name('custom-fields.index');
        Route::get('meta', [CustomFieldController::class, 'meta'])->name('custom-fields.meta');
        Route::post('/', [CustomFieldController::class, 'store'])->name('custom-fields.store');
        Route::get('{customField}', [CustomFieldController::class, 'show'])->name('custom-fields.show');
        Route::patch('{customField}', [CustomFieldController::class, 'update'])->name('custom-fields.update');
        Route::delete('{customField}', [CustomFieldController::class, 'destroy'])->name('custom-fields.destroy');
    });
