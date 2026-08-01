<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiters();
    }

    /**
     * محدِّدات المعدّل لنقاط المصادقة العامّة (تصلّب أمني — Stabilization).
     * تحدّ من التخمين وإساءة الاستخدام على المسارات غير المصادَق عليها، دون
     * تغيير سلوكها المشروع. تُطبَّق عبر وسيط throttle في مسارات الوحدة.
     */
    private function configureRateLimiters(): void
    {
        // تسجيل الدخول: محاولات معقولة لكل IP في الدقيقة (قفل الحساب يبقى طبقة ثانية).
        RateLimiter::for('auth-login', fn (Request $request) => [
            Limit::perMinute(30)->by($request->ip()),
        ]);

        // كلمة المرور (نسيان/إعادة تعيين عامّة): أشدّ، لمنع التعداد والإغراق.
        RateLimiter::for('auth-password', fn (Request $request) => [
            Limit::perMinute(10)->by($request->ip()),
        ]);
    }
}
