<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\HealthController;

/*
| مسارات وحدة Core — النواة العرضية (Auth/Permissions/Settings/Audit مستقبلاً).
| تُحمّل تلقائياً تحت البادئة /api عبر ModuleServiceProvider.
*/

Route::get('/health', HealthController::class)->name('core.health');
