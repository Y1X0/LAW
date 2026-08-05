<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * جدولة المهام الموحّدة (B3). تثبت أن مُجدوِل Laravel — المصدر الوحيد في routes/console.php،
 * الذي تشغّله خدمة law-scheduler عبر schedule:run — يسجّل المهام الدوريّة المطلوبة فقط:
 * التذكيرات والنسخ الاحتياطي مُجدوَلان، ومزامنة البصمة Pull **غير مُجدوَلة افتراضياً** (لأن
 * أجهزتها على شبكة المكتب لا يصلها المضيف السحابي — تحتاج مُشغّلاً داخلياً وعلَماً صريحاً).
 */
class ScheduleTest extends TestCase
{
    private function scheduleListing(): string
    {
        Artisan::call('schedule:list');

        return Artisan::output();
    }

    public function test_reminders_are_scheduled(): void
    {
        $this->assertStringContainsString('notifications:remind', $this->scheduleListing());
    }

    public function test_backup_is_scheduled(): void
    {
        $this->assertStringContainsString('backup:run', $this->scheduleListing());
    }

    public function test_biometric_pull_sync_is_not_scheduled_by_default(): void
    {
        // معطّلة على المضيف السحابي — لا تُجدوَل ما لم يُضبط BIOMETRIC_PULL_SCHEDULE_ENABLED.
        $this->assertStringNotContainsString('biometric:sync', $this->scheduleListing());
    }
}
