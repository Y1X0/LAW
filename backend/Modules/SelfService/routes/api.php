<?php

use Illuminate\Support\Facades\Route;
use Modules\SelfService\Http\Controllers\MyDashboardController;

/*
| مسارات وحدة SelfService (Epic 9) — سطح الخدمة الذاتية للموظف تحت /api/me.
| كل المسارات محميّة بـ: auth.token + employee.linked (موظف مرتبط) + صلاحية *_own.
| الوصول دائماً من Auth::user()->employee — لا وصول لبيانات موظف آخر.
*/

Route::middleware(['auth.token', 'employee.linked'])->prefix('me')->group(function () {
    // لوحة شخصية (#48)
    Route::middleware('permission:dashboard.view_own')
        ->get('dashboard', [MyDashboardController::class, 'show'])->name('me.dashboard');
});
