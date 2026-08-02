<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\Admin\AdminSummaryController;
use Modules\Core\Http\Controllers\Admin\AuditController;
use Modules\Core\Http\Controllers\Admin\BranchController;
use Modules\Core\Http\Controllers\Admin\DepartmentController;
use Modules\Core\Http\Controllers\Admin\SettingsController;
use Modules\Core\Http\Controllers\Admin\UserController;
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
// تصلّب أمني (Stabilization): محدِّدات معدّل على النقاط العامّة غير المصادَق عليها.
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth-login')->name('auth.login');
    Route::post('refresh', [AuthController::class, 'refresh'])->middleware('throttle:auth-login')->name('auth.refresh');
    Route::post('forgot-password', [PasswordController::class, 'forgot'])->middleware('throttle:auth-password')->name('auth.forgot');
    Route::post('reset-password', [PasswordController::class, 'reset'])->middleware('throttle:auth-password')->name('auth.reset');

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

    // ملاحظة أمنية: users.manage صلاحية «مالك المنصّة» الكاملة — من يملكها يستطيع
    // إسناد أي دور (بما فيه ما يمنح roles.manage) وإعادة تعيين كلمات المرور. تُمنَح
    // لدور المالك/المدير العام فقط. راجع docs/adr/006-users-manage-super-admin.md.
    Route::middleware('permission:users.manage')->group(function () {
        Route::get('users/{user}/roles', [UserRoleController::class, 'index'])->name('users.roles.index');
        Route::post('users/{user}/roles', [UserRoleController::class, 'store'])->name('users.roles.store');
        Route::delete('users/{user}/roles/{role}', [UserRoleController::class, 'destroy'])->name('users.roles.destroy');

        // إدارة المستخدمين لوحدة التحكّم (ADMIN-2) — إضافة فقط، بلا مساس بما سبق.
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('users/{user}/disable', [UserController::class, 'disable'])->name('users.disable');
        Route::post('users/{user}/enable', [UserController::class, 'enable'])->name('users.enable');
        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
        Route::post('users/{user}/employee', [UserController::class, 'linkEmployee'])->name('users.employee.link');
        Route::delete('users/{user}/employee', [UserController::class, 'unlinkEmployee'])->name('users.employee.unlink');

        // لوحة نظام وحدة التحكّم (ADMIN-4) — تجميع إحصاءات للقراءة فقط.
        Route::get('admin/summary', AdminSummaryController::class)->name('admin.summary');
    });

    // سجلّ التدقيق (ADMIN-5) — قراءة فقط، يفرض صلاحية audit.view القائمة.
    Route::middleware('permission:audit.view')->group(function () {
        Route::get('admin/audit', [AuditController::class, 'index'])->name('admin.audit.index');
    });

    // إعدادات المنصّة العامّة (ADMIN-5) — يفرض صلاحية settings.manage القائمة.
    Route::middleware('permission:settings.manage')->group(function () {
        Route::get('admin/settings', [SettingsController::class, 'index'])->name('admin.settings.index');
        Route::put('admin/settings', [SettingsController::class, 'update'])->name('admin.settings.update');
    });

    // الهيكل التنظيمي (M1) — إدارة الفروع والأقسام من الواجهة (بديل البذرة). صلاحية org.manage.
    Route::middleware('permission:org.manage')->group(function () {
        Route::get('branches', [BranchController::class, 'index'])->name('branches.index');
        Route::post('branches', [BranchController::class, 'store'])->name('branches.store');
        Route::get('branches/{branch}', [BranchController::class, 'show'])->name('branches.show');
        Route::put('branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
        Route::delete('branches/{branch}', [BranchController::class, 'destroy'])->name('branches.destroy');

        Route::get('departments', [DepartmentController::class, 'index'])->name('departments.index');
        Route::post('departments', [DepartmentController::class, 'store'])->name('departments.store');
        Route::get('departments/{department}', [DepartmentController::class, 'show'])->name('departments.show');
        Route::put('departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
        Route::delete('departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy');
    });
});
