<?php

use Illuminate\Support\Facades\Route;
use Modules\Backup\Http\Controllers\BackupController;

/*
| مسارات وحدة Backup (Phase 13) — تُحمّل تلقائياً تحت /api.
| إدارة النسخ الاحتياطية محميّة بصلاحية backup.manage (Owner). لا مسار استعادة (CLI فقط).
*/

Route::middleware(['auth.token', 'permission:backup.manage'])
    ->prefix('admin/backups')
    ->group(function () {
        Route::get('/', [BackupController::class, 'index'])->name('backups.index');
        Route::post('/', [BackupController::class, 'store'])->name('backups.store');
        Route::get('{backup}/download', [BackupController::class, 'download'])->name('backups.download');
    });
