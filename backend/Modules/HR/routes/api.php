<?php

use Illuminate\Support\Facades\Route;
use Modules\HR\Http\Controllers\EmployeeContractController;
use Modules\HR\Http\Controllers\EmployeeController;
use Modules\HR\Http\Controllers\EmployeeDocumentController;
use Modules\HR\Http\Controllers\EmployeeHistoryController;
use Modules\HR\Http\Controllers\PositionController;

/*
| مسارات وحدة HR — تُحمّل تلقائياً تحت /api عبر ModuleServiceProvider.
| إدارة الموظفين (#13) + السجلات الأساسية (#14). محميّة بالمصادقة + صلاحيات employees.*.
*/

Route::middleware('auth.token')->group(function () {
    // إدارة الموظفين (Issue #13)
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

    // السجلات الأساسية (Issue #14)
    Route::middleware('permission:employees.view')->group(function () {
        Route::get('positions', [PositionController::class, 'index'])->name('positions.index');
        Route::get('positions/{position}', [PositionController::class, 'show'])->name('positions.show');
        Route::get('employees/{employee}/contracts', [EmployeeContractController::class, 'index'])->name('employees.contracts.index');
        Route::get('employees/{employee}/documents', [EmployeeDocumentController::class, 'index'])->name('employees.documents.index');
        Route::get('employees/{employee}/history', [EmployeeHistoryController::class, 'index'])->name('employees.history');
    });

    Route::middleware('permission:employees.update')->group(function () {
        Route::post('positions', [PositionController::class, 'store'])->name('positions.store');
        Route::put('positions/{position}', [PositionController::class, 'update'])->name('positions.update');
        Route::delete('positions/{position}', [PositionController::class, 'destroy'])->name('positions.destroy');

        Route::post('employees/{employee}/contracts', [EmployeeContractController::class, 'store'])->name('employees.contracts.store');
        Route::put('employees/{employee}/contracts/{contract}', [EmployeeContractController::class, 'update'])->name('employees.contracts.update');

        Route::post('employees/{employee}/documents', [EmployeeDocumentController::class, 'store'])->name('employees.documents.store');
        Route::delete('employees/{employee}/documents/{document}', [EmployeeDocumentController::class, 'destroy'])->name('employees.documents.destroy');
    });
});
