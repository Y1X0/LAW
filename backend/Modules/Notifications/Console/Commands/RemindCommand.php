<?php

namespace Modules\Notifications\Console\Commands;

use Illuminate\Console\Command;
use Modules\Notifications\Services\ReminderService;

/**
 * توليد التذكيرات الزمنية (Notifications / Phase 8 · PR-4) — يُكتشف تلقائياً عبر
 * ModuleServiceProvider. قابل للتشغيل يدوياً، ويُجدوَل عبر schedule:run (Render cron).
 * قراءة فقط، ومنع تكرار داخلي — تشغيله مراراً آمن.
 */
class RemindCommand extends Command
{
    protected $signature = 'notifications:remind';

    protected $description = 'يولّد تذكيرات الجلسات القادمة والفواتير المستحقّة/المتأخّرة (بلا تكرار)';

    public function handle(ReminderService $service): int
    {
        $result = $service->run();

        $this->info(sprintf(
            'تذكيرات مُطلَقة — جلسات: %d، فواتير تقترب: %d، فواتير متأخّرة: %d.',
            $result['hearings'],
            $result['invoices_due'],
            $result['invoices_overdue'],
        ));

        return self::SUCCESS;
    }
}
