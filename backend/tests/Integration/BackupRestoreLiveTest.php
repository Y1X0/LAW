<?php

namespace Tests\Integration;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Backup\Contracts\DatabaseValidator;
use Modules\Backup\Exceptions\BackupValidationException;
use Modules\Backup\Models\Backup;
use Modules\Backup\Support\PgDumpDumper;
use Modules\Legal\Models\Client;
use Tests\TestCase;

/**
 * تحقّق حيّ من دورة النسخ والاستعادة على Postgres حقيقي (Operational Validation) — خارج CI
 * الرئيسي (SQLite). هذا هو دليل التسليم: نسخ → تعديل → حذف → استعادة → عودة البيانات.
 * يُشغَّل فقط حين BACKUP_LIVE_TEST=1 (سير عمل مخصّص مع خدمة Postgres + postgresql-client).
 * لا يستخدم RefreshDatabase — pg_restore --clean يُسقط الجداول (يتعارض مع معاملة الاختبار).
 */
class BackupRestoreLiveTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (env('BACKUP_LIVE_TEST') !== '1') {
            $this->markTestSkipped('اختبار الاستعادة الحيّ يعمل فقط في سير العمل المخصّص (BACKUP_LIVE_TEST=1).');
        }

        // Postgres حقيقي (من بيئة السير)، وتخزين النسخ محليّاً لهذا الاختبار (بلا R2/أسرار).
        config([
            'database.default' => 'pgsql',
            'filesystems.disks.backups' => ['driver' => 'local', 'root' => storage_path('app/it-backups')],
        ]);
    }

    public function test_backup_then_restore_recovers_deleted_data(): void
    {
        $marker = 'BEFORE-RESTORE-'.uniqid();
        Client::create(['name' => $marker, 'type' => 'individual', 'status' => 'active']);

        // 1) نسخة احتياطية (pg_dump حقيقي).
        $this->assertSame(0, Artisan::call('backup:run', ['--kind' => 'manual']), Artisan::output());
        $backup = Backup::where('status', 'completed')->latest('id')->first();
        $this->assertNotNull($backup, 'يجب أن تُنشأ نسخة مكتملة.');

        // 2) تغيير الحالة بعد النسخ: حذف العلامة وإضافة سجلّ جديد.
        Client::where('name', $marker)->delete();
        $after = 'AFTER-RESTORE-'.uniqid();
        Client::create(['name' => $after, 'type' => 'individual', 'status' => 'active']);
        $this->assertDatabaseMissing('clients', ['name' => $marker]);

        // 3) استعادة (pg_restore حقيقي — عملية مدمِّرة).
        $this->assertSame(0, Artisan::call('backup:restore', ['backup' => $backup->id, '--force' => true]), Artisan::output());
        DB::reconnect(); // امسح أي حالة اتصال قديمة بعد إعادة بناء المخطّط

        // 4) الدليل: العلامة السابقة عادت، وما أُضيف بعد النسخ اختفى.
        $this->assertDatabaseHas('clients', ['name' => $marker]);
        $this->assertDatabaseMissing('clients', ['name' => $after]);
    }

    /**
     * F3: المُدقّق الحقيقي (pg_restore --list) يقبل نسخة حقيقية صالحة ويرفض أرشيفاً تالفاً —
     * على Postgres/pg_restore حقيقيّين. (النسخة الصالحة تمرّ ضمن backup:run أعلاه؛ هنا نثبت
     * التمييز الصريح بين الصالح والتالف.)
     */
    public function test_validator_accepts_valid_dump_and_rejects_corrupt(): void
    {
        $validator = app(DatabaseValidator::class);

        // نسخة حقيقية صالحة (pg_dump -Fc) — يجب أن تمرّ التحقّق.
        $this->assertSame(0, Artisan::call('backup:run', ['--kind' => 'manual']), Artisan::output());
        $good = tempnam(sys_get_temp_dir(), 'goodbk').'.dump';
        (new PgDumpDumper)->dump($good);
        $validator->validate($good); // لا يرمي
        @unlink($good);

        // أرشيف تالف/غير صالح — يجب أن يُرفَض.
        $bad = tempnam(sys_get_temp_dir(), 'badbk').'.dump';
        file_put_contents($bad, 'this-is-not-a-valid-pg_dump-archive');
        try {
            $validator->validate($bad);
            $this->fail('كان يجب رفض الأرشيف التالف.');
        } catch (BackupValidationException) {
            // متوقّع
        } finally {
            @unlink($bad);
        }
    }
}
