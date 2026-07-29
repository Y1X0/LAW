<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// مزامنة أجهزة البصمة (Pull/التسوية) كل دقيقة — شبكة أمان تلتقط ما فات الـ Push (Issue #16).
Schedule::command('biometric:sync')->everyMinute()->withoutOverlapping();
