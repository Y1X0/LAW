<?php

use Illuminate\Support\Facades\Route;
use Modules\Notifications\Http\Controllers\NotificationController;

/*
| مسارات وحدة Notifications — تُحمّل تلقائياً تحت /api عبر ModuleServiceProvider.
| Phase 8 · PR-1: قراءة ذاتية وتعليم كمقروء، خلف صلاحية notifications.view_own.
| المسارات الثابتة (read-all/unread-count) قبل مسار {notification} لتفادي الالتقاط.
*/
Route::middleware(['auth.token', 'permission:notifications.view_own'])->group(function () {
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
    Route::patch('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
});
