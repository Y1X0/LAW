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

Route::get('/version', fn () => response()->json([
    'app' => config('app.name'),
    'version' => 'v1',
    'laravel' => app()->version(),
]));
