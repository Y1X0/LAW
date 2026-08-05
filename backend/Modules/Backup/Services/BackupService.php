<?php

namespace Modules\Backup\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Backup\Contracts\DatabaseDumper;
use Modules\Backup\Models\Backup;
use Modules\Core\Concerns\RecordsAudit;
use Modules\Core\Models\AuditLog;

/**
 * إنشاء النسخ الاحتياطية لقاعدة البيانات وإدارة الاحتفاظ (Phase 13). التفريغ عبر DatabaseDumper
 * (قابل للاختبار)، ثم رفع إلى قرص «backups» (دلو R2 منفصل)، مع تسجيل صفّ وتدقيق. الاحتفاظ
 * متدرّج (GFS) لكل نوع. الاستعادة ليست هنا — تُجرى عبر أمر CLI مُحصَّن + runbook (قرار أمان).
 */
class BackupService
{
    use RecordsAudit;

    /** الاحتفاظ المتدرّج: عدد النسخ المحفوظة لكل نوع (الأقدم يُقلَّم). */
    public const RETENTION = ['manual' => 30, 'daily' => 30, 'weekly' => 12, 'monthly' => 12];

    /** قرص تخزين النسخ (دلو R2 منفصل عن الوثائق). */
    public const DISK = 'backups';

    public function __construct(private readonly DatabaseDumper $dumper) {}

    /**
     * ينشئ نسخة: يفرّغ القاعدة → يرفعها → يسجّل النتيجة → يقلّم الاحتفاظ. يرمي عند الفشل
     * (ليظهر للـ cron)، مع تسجيل صفّ «failed» وتدقيق. $request اختياري (الـ cron بلا طلب).
     */
    public function run(string $kind = 'manual', string $trigger = 'scheduled', ?int $actorId = null, ?Request $request = null): Backup
    {
        $kind = in_array($kind, Backup::KINDS, true) ? $kind : 'manual';
        $backup = Backup::create([
            'disk' => self::DISK, 'kind' => $kind, 'status' => 'pending', 'trigger' => $trigger, 'created_by' => $actorId,
        ]);

        try {
            $tmp = tempnam(sys_get_temp_dir(), 'lawbk').'.dump';
            $this->dumper->dump($tmp);
            $size = filesize($tmp) ?: 0;

            $filename = 'law-backup-'.now()->format('Ymd-His').'-'.$kind.'.dump';
            $path = 'db/'.$filename;
            $stream = fopen($tmp, 'rb');
            Storage::disk(self::DISK)->put($path, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
            @unlink($tmp);

            $backup->update(['filename' => $filename, 'path' => $path, 'size_bytes' => $size, 'status' => 'completed']);
            $this->audit($request, 'backup_created', $backup->id, ['kind' => $kind, 'size_bytes' => $size, 'trigger' => $trigger]);
            $this->prune();
        } catch (\Throwable $e) {
            $backup->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000)]);
            $this->audit($request, 'backup_failed', $backup->id, ['kind' => $kind, 'error' => mb_substr($e->getMessage(), 0, 200)]);
            throw $e;
        }

        return $backup->fresh();
    }

    /** يقلّم النسخ المكتملة الزائدة عن حدّ الاحتفاظ لكل نوع (الأقدم أولاً) — ملفاً وصفّاً. */
    public function prune(): int
    {
        $deleted = 0;
        foreach (self::RETENTION as $kind => $keep) {
            $keepIds = Backup::where('kind', $kind)->where('status', 'completed')
                ->orderByDesc('id')->limit($keep)->pluck('id');

            $stale = Backup::where('kind', $kind)->where('status', 'completed')
                ->whereNotIn('id', $keepIds)->get();

            foreach ($stale as $b) {
                if ($b->path) {
                    Storage::disk($b->disk)->delete($b->path);
                }
                $b->delete();
                $deleted++;
            }
        }

        return $deleted;
    }

    /** تدقيق يعمل مع أو بلا طلب (الـ cron بلا مستخدم/طلب — يُسجَّل كـ system:backup). */
    private function audit(?Request $request, string $action, int $id, array $context): void
    {
        if ($request !== null) {
            $this->recordAudit($request, $action, Backup::class, $id, $context);

            return;
        }

        AuditLog::create([
            'user_id' => null,
            'action' => $action,
            'auditable_type' => Backup::class,
            'auditable_id' => $id,
            'old_values' => null,
            'new_values' => $context,
            'ip_address' => null,
            'user_agent' => 'system:backup',
        ]);
    }
}
