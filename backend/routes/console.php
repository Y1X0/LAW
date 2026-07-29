<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// تسوية دورية لأجهزة البصمة (نمط pull) كشبكة أمان للـ Push (#16, ADR-003).
Schedule::command('attendance:sync-biometric')->everyFiveMinutes()->withoutOverlapping();
