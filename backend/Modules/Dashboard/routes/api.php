<?php

use Illuminate\Support\Facades\Route;
use Modules\Dashboard\Http\Controllers\DashboardController;

/*
| مسارات وحدة Dashboard — تُحمّل تلقائياً تحت /api عبر ModuleServiceProvider.
| لوحة المؤشرات الإدارية الشاملة (Phase 7) — قراءة فقط خلف حارس واحد.
*/

Route::middleware('auth.token')->group(function () {
    Route::middleware('permission:dashboard.view_management')
        ->get('dashboard/summary', [DashboardController::class, 'summary'])->name('dashboard.summary');
});
