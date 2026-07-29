<?php

use Illuminate\Support\Facades\Route;
use Modules\HR\Http\Controllers\EmployeeController;

/*
| مسارات وحدة HR — تُحمّل تلقائياً تحت /api عبر ModuleServiceProvider.
| إدارة الموظفين (Issue #13). محميّة بالمصادقة + صلاحيات employees.*.
*/

Route::middleware('auth.token')->group(function () {
    Route::middleware('permission:employees.view')->group(function () {
        Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
        Route::get('employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
    });

    Route::middleware('permission:employees.create')
        ->post('employees', [EmployeeController::class, 'store'])->name('employees.store');

    Route::middleware('permission:employees.update')
        ->put('employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');

    Route::middleware('permission:employees.delete')
        ->delete('employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');
});
