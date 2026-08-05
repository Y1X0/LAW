<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ===========================================================================
// جدولة المهام (B3) — مصدر واحد. يشغّلها في الإنتاج خدمة Render واحدة (law-scheduler)
// عبر `php artisan schedule:run` كل دقيقة. إضافة مهمة مستقبلاً = سطر واحد هنا لا تعديل
// render.yaml. كل الأوامر أدناه idempotent ومانعة للتكرار (آمنة عند أي تشغيل يدوي/مكرّر).
// ===========================================================================

// تذكيرات الجلسات/الفواتير يوميّاً (Phase 8 · PR-4). تُنشئ إشعارات داخل النظام، وترسل بريداً
// للأنواع المُدرَجة بالقائمة البيضاء (B2 · PR-3) — لذا خدمة الجدولة تحتاج أسرار MAIL_*.
Schedule::command('notifications:remind')->dailyAt('06:00')->withoutOverlapping();

// النسخ الاحتياطي اليومي (Phase 13 · B1). pg_dump → دلو R2 منفصل + تقليم GFS. النوع يُشتقّ
// من التاريخ. كان cron مباشراً؛ وُحِّد هنا تحت المُوزّع (B3) — تحتاج الخدمة أسرار R2_BACKUP_*.
Schedule::command('backup:run')->dailyAt('03:00')->withoutOverlapping();

// مزامنة أجهزة البصمة (Pull/التسوية، Issue #16) — **معطّلة افتراضياً**. الأجهزة على شبكة
// المكتب (LAN) لا يصل إليها المضيف السحابي (Render)، والمسار الحيّ هو Push (الأجهزة → API).
// لتفعيل شبكة أمان الـ Pull يلزم مُشغّل schedule داخل شبكة الشركة (أو VPN) يضبط العلَم أدناه؛
// عندها فقط تُجدوَل كل دقيقة. لا تُفعَّل على Render. (الأمر يقتصر أصلاً على أجهزة api_mode=pull.)
if (filter_var(env('BIOMETRIC_PULL_SCHEDULE_ENABLED', false), FILTER_VALIDATE_BOOLEAN)) {
    Schedule::command('biometric:sync')->everyMinute()->withoutOverlapping();
}
