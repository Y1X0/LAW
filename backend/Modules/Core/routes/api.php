<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\Auth\AuthController;
use Modules\Core\Http\Controllers\Auth\PasswordController;
use Modules\Core\Http\Controllers\HealthController;

/*
| مسارات وحدة Core — النواة العرضية (Auth/Permissions/Settings/Audit).
| تُحمّل تلقائياً تحت البادئة /api عبر ModuleServiceProvider.
*/

Route::get('/health', HealthController::class)->name('core.health');

// المصادقة (Issue #11) — لا RBAC هنا (يأتي في #12).
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('refresh', [AuthController::class, 'refresh'])->name('auth.refresh');
    Route::post('forgot-password', [PasswordController::class, 'forgot'])->name('auth.forgot');
    Route::post('reset-password', [PasswordController::class, 'reset'])->name('auth.reset');

    Route::middleware('auth.token')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('me', [AuthController::class, 'me'])->name('auth.me');
    });
});
