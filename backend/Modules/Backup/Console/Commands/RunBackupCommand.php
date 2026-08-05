<?php

namespace Modules\Backup\Console\Commands;

use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Modules\Backup\Services\BackupService;

/**
 * ينشئ نسخة احتياطية لقاعدة البيانات (Phase 13). يُشغَّل يوميّاً عبر Render cron. النوع يُشتقّ
 * من التاريخ لدوران GFS بملفٍ واحد يوميّاً: الأوّل من الشهر ⇒ شهري، الأحد ⇒ أسبوعي، وإلّا يومي.
 * الأمر آمن للتكرار (كل تشغيل ينشئ نسخة مستقلّة)، ويقلّم الزائد تلقائياً.
 */
class RunBackupCommand extends Command
{
    protected $signature = 'backup:run {--kind= : manual|daily|weekly|monthly (افتراضياً يُشتقّ من التاريخ)}';

    protected $description = 'إنشاء نسخة احتياطية لقاعدة البيانات ورفعها إلى تخزين النسخ مع تقليم الاحتفاظ.';

    public function handle(BackupService $service): int
    {
        $kind = $this->option('kind') ?: $this->deriveKind();
        $this->info("بدء النسخ الاحتياطي ({$kind})…");

        try {
            $backup = $service->run($kind, 'scheduled');
        } catch (\Throwable $e) {
            $this->error('فشل النسخ الاحتياطي: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("تمّت النسخة: {$backup->filename} ({$backup->size_bytes} بايت).");

        return self::SUCCESS;
    }

    /** ملف واحد يوميّاً؛ نوعه أعلى مستوى يستوفيه ذلك اليوم (GFS). */
    private function deriveKind(): string
    {
        $now = now();
        if ((int) $now->day === 1) {
            return 'monthly';
        }
        if ($now->dayOfWeek === CarbonInterface::SUNDAY) {
            return 'weekly';
        }

        return 'daily';
    }
}
