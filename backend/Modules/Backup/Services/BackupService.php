<?php

namespace Modules\Backup\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\Backup\Contracts\DatabaseDumper;
use Modules\Backup\Contracts\DatabaseRestorer;
use Modules\Backup\Contracts\DatabaseValidator;
use Modules\Backup\Exceptions\BackupValidationException;
use Modules\Backup\Exceptions\RestoreVerificationException;
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

    public function __construct(
        private readonly DatabaseDumper $dumper,
        private readonly DatabaseRestorer $restorer,
        private readonly DatabaseValidator $validator,
    ) {}

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

            // تحقّق ما قبل الرفع (F3): خروج pg_dump بنجاح لا يكفي — نتأكّد أنّ الأرتيفاكت غير
            // فارغ وأرشيف ‎-Fc‎ صالح بنيويّاً (pg_restore --list) قبل رفعه ووسمه «مكتملاً»،
            // فلا نُبلّغ عن نسخة ناجحة غير قابلة للاستعادة. الفشل يرمي فيُسجَّل الصفّ «failed»
            // (كنمط fail-loud في الاستعادة) ولا يُرفع شيء.
            if ($size <= 0) {
                throw new BackupValidationException('التفريغ فارغ (0 بايت) — النسخة غير صالحة.');
            }
            $this->validator->validate($tmp);

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

    /**
     * يستعيد قاعدة البيانات من نسخة (عملية مدمِّرة — تستبدل البيانات الحالية). ينزّل الملف من
     * تخزين النسخ ثم يشغّل pg_restore. يُدقَّق (backup_restored) — يُكتب في القاعدة المُستعادة.
     *
     * @throws \RuntimeException|\Throwable
     */
    public function restore(Backup $backup, ?Request $request = null): void
    {
        if ($backup->status !== 'completed' || ! $backup->path) {
            throw new \RuntimeException('النسخة غير مكتملة — لا يمكن الاستعادة منها.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'lawrs').'.dump';
        $src = Storage::disk($backup->disk)->readStream($backup->path);
        $dst = fopen($tmp, 'wb');
        stream_copy_to_stream($src, $dst);
        fclose($dst);
        if (is_resource($src)) {
            fclose($src);
        }

        try {
            $this->restorer->restore($tmp);
        } finally {
            @unlink($tmp);
        }

        // تحقّق ما بعد الاستعادة (M4): نجاح العملية وحده لا يكفي — نتأكّد أنّ المخطّط الأساسي
        // موجود فعلاً كي لا نُبلّغ عن «نجاح كاذب» عند استعادة ناقصة. يُرمى استثناء عند الفشل
        // فلا يُكتب تدقيق backup_restored ولا يُعتبر الأمر ناجحاً.
        $this->verifyRestore();

        $this->audit($request, 'backup_restored', $backup->id, ['filename' => $backup->filename]);
    }

    /** الجداول الأساسية التي يجب أن تكون موجودة بعد أي استعادة صحيحة (العمود الفقري العلائقي). */
    private const CRITICAL_TABLES = [
        'users', 'clients', 'cases', 'employees', 'invoices', 'payments', 'audit_logs', 'migrations',
    ];

    /**
     * تحقّق خفيف ما بعد الاستعادة: كل جدول أساسي موجود، وجدول الهجرات غير فارغ (المخطّط
     * محمّل فعلاً). لا يفحص منطق العمل ولا يغيّر البيانات — بوّابة سلامة فقط.
     */
    private function verifyRestore(): void
    {
        // بعد استعادة Postgres يُعاد إنشاء الجداول؛ نُعيد الاتصال لتفادي مخزون OID قديم.
        // (على SQLite في الاختبارات لا نُعيد الاتصال كي لا تُمحى قاعدة :memory:.)
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::reconnect();
        }

        foreach (self::CRITICAL_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                throw new RestoreVerificationException(
                    'الاستعادة غير مكتملة: جدول أساسي مفقود بعد الاستعادة ('.$table.').',
                );
            }
        }

        if (DB::table('migrations')->count() < 1) {
            throw new RestoreVerificationException('الاستعادة غير مكتملة: جدول الهجرات فارغ بعد الاستعادة.');
        }
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
