<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (النواة)
|--------------------------------------------------------------------------
| المسارات العامة على مستوى النظام. مسارات كل وحدة تُحمّل تلقائياً من
| Modules/<Name>/routes/api.php عبر App\Providers\ModuleServiceProvider.
| هذا الملف يبقى خفيفاً — لا يحتوي منطق أي وحدة (احترام حدود الوحدات).
*/

// هويّة الإصدار — خفيفة، بلا اتصال قاعدة بيانات. تكشف النسخة المنشورة (commit/version)
// لمعرفة أي إصدار يعمل فعلاً على الخادم. بصمة الـcommit من RENDER_GIT_COMMIT (null إن غابت).
Route::get('/version', fn () => response()->json([
    'app' => config('app.name'),
    'version' => config('app.version'),
    'commit' => config('app.commit'),
    'environment' => config('app.env'),
    'laravel' => app()->version(),
]));
