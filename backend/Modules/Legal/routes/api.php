<?php

use Illuminate\Support\Facades\Route;
use Modules\Legal\Http\Controllers\ClientController;

/*
| مسارات وحدة Legal — تُحمّل تلقائياً تحت /api عبر ModuleServiceProvider.
| LC-1: إدارة العملاء (clients.view/create/update). لا حذف فعلي — التعطيل عبر الحالة.
*/
Route::middleware('auth.token')->group(function () {
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
});
