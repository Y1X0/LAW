<?php

namespace Modules\Backup\Console\Commands;

use Illuminate\Console\Command;
use Modules\Backup\Models\Backup;
use Modules\Backup\Services\BackupService;

/**
 * يستعيد قاعدة البيانات من نسخة احتياطية (Phase 13). عملية **مدمِّرة**: تستبدل كل البيانات
 * الحالية بمحتوى النسخة. تتطلّب تأكيداً (‎--force‎ أو موافقة تفاعلية) — لا تشغيل عشوائي.
 * لا تُستدعى من الواجهة إطلاقاً (قرار أمان)؛ تُشغَّل يدويّاً على السيرفر مع سجلّ كامل.
 */
class RestoreBackupCommand extends Command
{
    protected $signature = 'backup:restore {backup : معرّف النسخة} {--force : تأكيد الاستعادة المدمِّرة دون سؤال}';

    protected $description = 'استعادة قاعدة البيانات من نسخة احتياطية (مدمِّرة — تتطلّب --force أو تأكيداً).';

    public function handle(BackupService $service): int
    {
        $backup = Backup::find($this->argument('backup'));
        if ($backup === null) {
            $this->error('النسخة غير موجودة.');

            return self::FAILURE;
        }
        if ($backup->status !== 'completed') {
            $this->error("النسخة غير مكتملة (الحالة: {$backup->status}) — لا يمكن الاستعادة منها.");

            return self::FAILURE;
        }

        $this->warn('⚠️  الاستعادة تمحو كل البيانات الحالية وتستبدلها بمحتوى النسخة.');
        $this->line("النسخة: {$backup->filename} · النوع: {$backup->kind} · التاريخ: {$backup->created_at}");

        if (! $this->option('force') && ! $this->confirm('هل أنت متأكّد من متابعة الاستعادة؟', false)) {
            $this->info('أُلغيت الاستعادة.');

            return self::SUCCESS;
        }

        $this->warn('بدء الاستعادة…');
        try {
            $service->restore($backup);
        } catch (\Throwable $e) {
            $this->error('فشلت الاستعادة: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('✅ تمّت الاستعادة بنجاح. سُجِّل الحدث في التدقيق (backup_restored).');

        return self::SUCCESS;
    }
}
