<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        $this->configurePasswordPolicy();
    }

    /**
     * M10: سياسة كلمة مرور قويّة **مركزيّة**. تُستهلَك عبر Password::defaults() في كل مسارات
     * تعيين كلمة المرور (إعادة تعيين ذاتيّة/إداريّة + إنشاء مستخدم)، فمصدر واحد للقاعدة.
     * 12 محرفاً على الأقل + أحرف كبيرة وصغيرة + أرقام + رموز. (فحص التسريب uncompromised()
     * مُستبعَد عمداً: يضيف اعتماداً شبكيّاً في مسار المصادقة الحرِج لنظام داخلي لشركة واحدة.)
     */
    private function configurePasswordPolicy(): void
    {
        Password::defaults(fn () => Password::min(12)->mixedCase()->numbers()->symbols());
    }

    /**
     * محدِّدات المعدّل لنقاط المصادقة العامّة (تصلّب أمني — Stabilization).
     * تحدّ من التخمين وإساءة الاستخدام على المسارات غير المصادَق عليها، دون
     * تغيير سلوكها المشروع. تُطبَّق عبر وسيط throttle في مسارات الوحدة.
     */
    private function configureRateLimiters(): void
    {
        // تسجيل الدخول (M9): حدّان — لكل IP، **ولكل بريد** عند وجوده. المفتاح بالبريد يوقف
        // التخمين الموزّع عبر عناوين IP متبدّلة (الحدّ بالـ IP وحده لا يمنعه). حدّ البريد (10/دقيقة)
        // فوق عتبة قفل الحساب (5) فلا يتعارض معه. مسار refresh يشترك بنفس المحدِّد بلا بريد فيبقى
        // محدوداً بالـ IP فقط. قفل الحساب يبقى طبقة ثالثة.
        RateLimiter::for('auth-login', function (Request $request) {
            $limits = [Limit::perMinute(20)->by($request->ip())];
            $email = $request->input('email');
            if (is_string($email) && $email !== '') {
                $limits[] = Limit::perMinute(10)->by('email:'.mb_strtolower($email));
            }

            return $limits;
        });

        // كلمة المرور (نسيان/إعادة تعيين عامّة): أشدّ، لمنع التعداد والإغراق.
        RateLimiter::for('auth-password', fn (Request $request) => [
            Limit::perMinute(10)->by($request->ip()),
        ]);

        // Webhook البصمة (غير مصادَق عليه بمستخدم؛ يُصادَق بتوكن الجهاز): حدّ لكل جهاز
        // لمنع الإغراق/الاستنزاف غير المصادَق عليه (لا يعطّل الدفع الطبيعي للأجهزة).
        RateLimiter::for('biometric-webhook', fn (Request $request) => [
            Limit::perMinute(120)->by('device:'.$request->route('device')),
        ]);
    }
}
