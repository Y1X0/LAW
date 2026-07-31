<?php

use Illuminate\Support\Facades\Route;
use Modules\Legal\Http\Controllers\CaseController;
use Modules\Legal\Http\Controllers\ClientController;

/*
| مسارات وحدة Legal — تُحمّل تلقائياً تحت /api عبر ModuleServiceProvider.
| LC-1: العملاء (clients.*) · LC-2: القضايا + الإسناد (cases.*) بعزل view_own.
*/
Route::middleware('auth.token')->group(function () {
    // ---- LC-1: العملاء ----
    Route::middleware('permission:clients.view')->group(function () {
        Route::get('clients', [ClientController::class, 'index'])->name('clients.index');
        Route::get('clients/{client}', [ClientController::class, 'show'])->name('clients.show');
    });

    Route::middleware('permission:clients.create')
        ->post('clients', [ClientController::class, 'store'])->name('clients.store');

    Route::middleware('permission:clients.update')->group(function () {
        Route::put('clients/{client}', [ClientController::class, 'update'])->name('clients.update');
        Route::patch('clients/{client}/status', [ClientController::class, 'setStatus'])->name('clients.status');
    });

    // ---- LC-2: القضايا ----
    // القراءة متاحة للإدارة (view_all) أو المحامي المسند (view_own) — والتنطيق داخل المتحكّم.
    Route::middleware('permission:cases.view_own,cases.view_all')->group(function () {
        Route::get('cases', [CaseController::class, 'index'])->name('cases.index');
        Route::get('cases/{case}', [CaseController::class, 'show'])->name('cases.show');
    });

    Route::middleware('permission:cases.create')
        ->post('cases', [CaseController::class, 'store'])->name('cases.store');

    Route::middleware('permission:cases.update')
        ->put('cases/{case}', [CaseController::class, 'update'])->name('cases.update');

    Route::middleware('permission:cases.assign')->group(function () {
        Route::post('cases/{case}/assign', [CaseController::class, 'assign'])->name('cases.assign');
        Route::delete('cases/{case}/assign/{employee}', [CaseController::class, 'unassign'])->name('cases.unassign');
    });

    Route::middleware('permission:cases.close')
        ->post('cases/{case}/close', [CaseController::class, 'close'])->name('cases.close');
});
