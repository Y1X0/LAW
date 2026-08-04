<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// مزامنة أجهزة البصمة (Pull/التسوية) كل دقيقة — شبكة أمان تلتقط ما فات الـ Push (Issue #16).
Schedule::command('biometric:sync')->everyMinute()->withoutOverlapping();

// تذكيرات الجلسات/الفواتير يوميّاً (Phase 8 · PR-4) — خامل حتى يشغّل Render cron الأمر
// schedule:run؛ الأمر نفسه قابل للتشغيل يدويّاً ومانع للتكرار داخليّاً.
Schedule::command('notifications:remind')->dailyAt('06:00')->withoutOverlapping();
