<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\Auth\AuthController;
use Modules\Core\Http\Controllers\Auth\PasswordController;
use Modules\Core\Http\Controllers\HealthController;
use Modules\Core\Http\Controllers\Rbac\PermissionController;
use Modules\Core\Http\Controllers\Rbac\RoleController;
use Modules\Core\Http\Controllers\Rbac\UserRoleController;

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

// RBAC — إدارة الأدوار والصلاحيات (Issue #12). محميّ بالمصادقة + الصلاحيات.
Route::middleware('auth.token')->group(function () {
    Route::middleware('permission:roles.manage')->group(function () {
        Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');
        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
        Route::get('roles/{role}', [RoleController::class, 'show'])->name('roles.show');
        Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
        Route::put('roles/{role}/permissions', [RoleController::class, 'syncPermissions'])->name('roles.permissions.sync');
    });

    Route::middleware('permission:users.manage')->group(function () {
        Route::get('users/{user}/roles', [UserRoleController::class, 'index'])->name('users.roles.index');
        Route::post('users/{user}/roles', [UserRoleController::class, 'store'])->name('users.roles.store');
        Route::delete('users/{user}/roles/{role}', [UserRoleController::class, 'destroy'])->name('users.roles.destroy');
    });
});
