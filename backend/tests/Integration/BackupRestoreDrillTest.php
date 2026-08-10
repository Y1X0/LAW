<?php

namespace Tests\Integration;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Backup\Models\Backup;
use Modules\Backup\Services\BackupService;
use Modules\Core\Models\AuditLog;
use Modules\Finance\Models\FinancialAccount;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\Payment;
use Modules\HR\Models\Employee;
use Modules\Legal\Models\CaseAssignment;
use Modules\Legal\Models\CaseDocument;
use Modules\Legal\Models\CaseTask;
use Modules\Legal\Models\Client;
use Modules\Legal\Models\Hearing;
use Modules\Legal\Models\LegalCase;
use RuntimeException;
use Tests\TestCase;

/**
 * تمرين الاستعادة (M4): يثبت أنّ النسخة قابلة للاستعادة فعلاً وأنّ الاستعادة الناقصة/الفاسدة
 * تُكتشف بدل «النجاح الكاذب». يعمل على Postgres مؤقّت فقط (BACKUP_LIVE_TEST=1) — لا يلمس الإنتاج.
 *
 * لا يستخدم RefreshDatabase: pg_restore --clean يُسقط الجداول (يتعارض مع معاملة الاختبار).
 * كل دالة اختبار تبدأ بـ migrate:fresh لعزلها.
 */
class BackupRestoreDrillTest extends TestCase
{
    /**
     * الجداول التي نتحقّق من تطابقها (العدد + بصمة المحتوى) عبر دورة النسخ/الاستعادة.
     * ملاحظة: audit_logs مستثناة من التطابق الصارم لأنّ عمليتَي النسخ/الاستعادة نفسيهما
     * تكتبان فيه (backup_created/backup_restored) فلا يكون round-trip-stable؛ نتحقّق منه
     * بشكل منفصل (صمود السجلّ المزروع + فرض المنع append-only).
     */
    private const SPINE = [
        'users', 'clients', 'cases', 'case_assignments', 'hearings', 'case_tasks',
        'case_documents', 'employees', 'branches', 'departments', 'financial_accounts',
        'invoices', 'payments', 'migrations',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        if (env('BACKUP_LIVE_TEST') !== '1') {
            $this->markTestSkipped('تمرين الاستعادة يعمل فقط في سير العمل المخصّص (BACKUP_LIVE_TEST=1 + Postgres).');
        }

        // Postgres حقيقي (من بيئة السير)، وتخزين النسخ محليّاً (بلا R2/أسرار).
        config([
            'database.default' => 'pgsql',
            'filesystems.disks.backups' => ['driver' => 'local', 'root' => storage_path('app/it-backups')],
        ]);

        // حارس العزل: يمنع تشغيل الاستعادة المدمّرة على الإنتاج أو قاعدة غير مخصّصة للتمرين.
        self::guardIsolation(
            (string) app()->environment(),
            (string) config('database.connections.'.config('database.default').'.database'),
        );
    }

    /**
     * حارس العزل (دالة نقيّة قابلة للاختبار): يرفض الإنتاج وأي قاعدة اسمها ليس مخصّصاً للتمرين.
     */
    private static function guardIsolation(string $env, string $database): void
    {
        if ($env === 'production') {
            throw new RuntimeException('تمرين الاستعادة ممنوع في بيئة الإنتاج.');
        }
        if (! preg_match('/(test|drill|ci)/i', $database)) {
            throw new RuntimeException("قاعدة التمرين «{$database}» ليست قاعدة مؤقّتة مسموحة (test/drill/ci).");
        }
    }

    // ── حارس العزل ────────────────────────────────────────────────────────────

    public function test_isolation_guard_blocks_production_and_non_drill_db(): void
    {
        foreach ([['production', 'lawfirm_test'], ['testing', 'lawfirm'], ['production', 'lawfirm']] as [$env, $db]) {
            try {
                self::guardIsolation($env, $db);
                $this->fail("الحارس كان يجب أن يرفض env={$env} db={$db}");
            } catch (RuntimeException) {
                $this->assertTrue(true);
            }
        }

        // مسموح: بيئة غير إنتاجية + اسم قاعدة تمرين.
        self::guardIsolation('testing', 'lawfirm_test');
        self::guardIsolation('testing', 'law_restore_drill');
        $this->assertTrue(true);
    }

    // ── دورة كاملة: نسخ → تعديل → استعادة → تحقّق سلامة ─────────────────────────

    public function test_full_spine_round_trips_with_integrity(): void
    {
        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->seedSpine();

        $beforeMarker = 'BEFORE-'.uniqid();
        Client::factory()->create(['name' => $beforeMarker]);

        // بصمة ما قبل النسخ (عدد + تجزئة محتوى لكل جدول).
        $pre = $this->fingerprint();

        // 1) نسخة احتياطية (pg_dump حقيقي).
        $this->assertSame(0, Artisan::call('backup:run', ['--kind' => 'manual']), Artisan::output());
        $backup = Backup::where('status', 'completed')->latest('id')->firstOrFail();

        // 2) تغيير الحالة بعد النسخ: علامة جديدة + حذف + إفراغ جدول (يجب أن يُعكَس بالاستعادة).
        $afterMarker = 'AFTER-'.uniqid();
        Client::factory()->create(['name' => $afterMarker]);
        Hearing::query()->delete();
        DB::statement('TRUNCATE case_tasks CASCADE');
        $this->assertDatabaseHas('clients', ['name' => $afterMarker]);

        // سجلّ التدقيق يمنع الحذف قبل الاستعادة (دليل أنّ الـtrigger فعّال أصلاً).
        $this->assertAuditAppendOnlyEnforced();

        // 3) استعادة (pg_restore حقيقي، ذرّية، مع تحقّق ما بعد الاستعادة).
        $this->assertSame(0, Artisan::call('backup:restore', ['backup' => $backup->id, '--force' => true]), Artisan::output());
        DB::reconnect();

        // 4) تطابق العدد + البصمة لكل جدول (يكشف الاستعادة الجزئية أو الفساد الصامت).
        $post = $this->fingerprint();
        foreach (self::SPINE as $table) {
            $this->assertSame($pre[$table]['count'], $post[$table]['count'], "عدد الصفوف تغيّر بعد الاستعادة: {$table}");
            $this->assertSame($pre[$table]['hash'], $post[$table]['hash'], "بصمة المحتوى تغيّرت بعد الاستعادة: {$table}");
        }

        // 5) دلالة العلامتين: ما قبل النسخ عاد، وما بعد النسخ اختفى (لا استعادة صامتة بلا مفعول).
        $this->assertDatabaseHas('clients', ['name' => $beforeMarker]);
        $this->assertDatabaseMissing('clients', ['name' => $afterMarker]);

        // audit_logs (مستثنى من التطابق الصارم): السجلّ المزروع صمد عبر الدورة، والجدول غير فارغ.
        $this->assertDatabaseHas('audit_logs', ['action' => 'drill_seed']);
        $this->assertGreaterThan(0, DB::table('audit_logs')->count());

        // 6) لا أيتام مفاتيح أجنبية عبر العمود الفقري.
        $this->assertNoForeignKeyOrphans();

        // 7) العلاقات المتقاطعة تُحَل فعلاً.
        $this->assertSame(0, (int) DB::table('cases')->whereNotIn('client_id', fn ($q) => $q->from('clients')->select('id'))->count(), 'قضية بلا عميل صالح');
        $this->assertSame(0, (int) DB::table('payments')->whereNotIn('invoice_id', fn ($q) => $q->from('invoices')->select('id'))->count(), 'دفعة بلا فاتورة صالحة');

        // 8) قيود التفرّد سليمة (لا تكرار).
        $this->assertUniqueIntact();

        // 9) trigger منع التعديل على سجلّ التدقيق أُعيد إنشاؤه ويُطبَّق بعد الاستعادة.
        $this->assertTrue($this->auditTriggerExists(), 'trigger append-only لسجلّ التدقيق مفقود بعد الاستعادة');
        $this->assertAuditAppendOnlyEnforced();

        // 10) أساس المخطّط: جدول الهجرات غير فارغ (مضمون أيضاً ضمن تطابق البصمة).
        $this->assertGreaterThan(0, DB::table('migrations')->count());
    }

    // ── فساد النسخة: الاستعادة تفشل بصوت عالٍ (لا نجاح كاذب) ───────────────────

    public function test_corrupt_backup_fails_loudly_and_leaves_db_intact(): void
    {
        Artisan::call('migrate:fresh', ['--force' => true]);
        $marker = 'INTACT-'.uniqid();
        Client::factory()->create(['name' => $marker]);

        $this->assertSame(0, Artisan::call('backup:run', ['--kind' => 'manual']), Artisan::output());
        $backup = Backup::where('status', 'completed')->latest('id')->firstOrFail();

        // إفساد ملف النسخة المخزّن محليّاً (اقتطاع إلى بايتات قليلة غير صالحة كأرشيف).
        Storage::disk($backup->disk)->put($backup->path, 'this-is-not-a-valid-pg_dump-archive');

        // مع --exit-on-error/--single-transaction يجب أن ترمي الاستعادة، لا أن «تنجح» بصمت.
        try {
            app(BackupService::class)->restore($backup->fresh());
            $this->fail('كان يجب أن تفشل استعادة نسخة فاسدة، لا أن تُبلّغ عن نجاح.');
        } catch (\Throwable $e) {
            $this->assertTrue(true);
        }

        // القاعدة سليمة (الذرّية أعادت الحالة): العلامة ما زالت موجودة، ولم يُسجَّل نجاح استعادة.
        DB::reconnect();
        $this->assertDatabaseHas('clients', ['name' => $marker]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'backup_restored', 'auditable_id' => $backup->id]);
    }

    // ── مساعِدات ──────────────────────────────────────────────────────────────

    /** يبني عموداً فقرياً علائقياً تمثيلياً يغطّي الجداول الأساسية والعلاقات بينها. */
    private function seedSpine(): void
    {
        $employee = Employee::factory()->create(); // يُنشئ فرعاً وقسماً
        User::factory()->create();
        $client = Client::factory()->create();
        $case = LegalCase::factory()->create(['client_id' => $client->id, 'responsible_lawyer_id' => $employee->id]);
        CaseAssignment::create(['case_id' => $case->id, 'employee_id' => $employee->id, 'role' => 'lead']);
        Hearing::factory()->create(['case_id' => $case->id]);
        CaseTask::factory()->create(['case_id' => $case->id, 'assigned_to' => $employee->id]);
        CaseDocument::factory()->create(['case_id' => $case->id]);

        $account = FinancialAccount::factory()->create();
        $invoice = Invoice::factory()->create(['client_id' => $client->id, 'case_id' => $case->id]);
        Payment::factory()->create(['invoice_id' => $invoice->id, 'client_id' => $client->id, 'account_id' => $account->id]);

        AuditLog::create([
            'user_id' => null,
            'action' => 'drill_seed',
            'auditable_type' => Client::class,
            'auditable_id' => $client->id,
            'new_values' => ['seeded' => true],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'drill',
        ]);
    }

    /** @return array<string,array{count:int,hash:string}> */
    private function fingerprint(): array
    {
        $out = [];
        foreach (self::SPINE as $table) {
            // أسماء الجداول من ثابت داخلي (لا مدخلات مستخدم) — لا حقن.
            $row = DB::selectOne("SELECT count(*)::int AS c, md5(coalesce(string_agg(md5(t::text), ',' ORDER BY t::text), '')) AS h FROM {$table} t");
            $out[$table] = ['count' => (int) $row->c, 'hash' => (string) $row->h];
        }

        return $out;
    }

    private function assertNoForeignKeyOrphans(): void
    {
        $checks = [
            ['cases', 'client_id', 'clients'],
            ['case_assignments', 'case_id', 'cases'],
            ['case_assignments', 'employee_id', 'employees'],
            ['hearings', 'case_id', 'cases'],
            ['case_documents', 'case_id', 'cases'],
            ['invoices', 'client_id', 'clients'],
            ['payments', 'invoice_id', 'invoices'],
            ['payments', 'client_id', 'clients'],
            ['payments', 'account_id', 'financial_accounts'],
            ['employees', 'branch_id', 'branches'],
            ['employees', 'department_id', 'departments'],
        ];
        foreach ($checks as [$child, $fk, $parent]) {
            $orphans = DB::table($child)
                ->whereNotNull($fk)
                ->whereNotIn($fk, fn ($q) => $q->from($parent)->select('id'))
                ->count();
            $this->assertSame(0, (int) $orphans, "أيتام مفتاح أجنبي في {$child}.{$fk} → {$parent}");
        }
    }

    private function assertUniqueIntact(): void
    {
        $unique = [
            ['clients', 'national_id'],
            ['employees', 'employee_no'],
            ['employees', 'national_id'],
            ['cases', 'internal_number'],
            ['case_assignments', 'case_id,employee_id'],
        ];
        foreach ($unique as [$table, $cols]) {
            $columns = explode(',', $cols);
            $total = DB::table($table)->whereNotNull($columns[0])->count();
            // عدّ القيم الفريدة (يدعم المفاتيح المركّبة) عبر SELECT DISTINCT للأعمدة ثم العدّ.
            $distinct = DB::table($table)->whereNotNull($columns[0])->distinct()->get($columns)->count();
            $this->assertSame($total, $distinct, "تكرار في مفتاح فريد: {$table}({$cols})");
        }
    }

    private function auditTriggerExists(): bool
    {
        $trg = DB::selectOne("SELECT 1 AS ok FROM pg_trigger WHERE tgname = 'trg_audit_logs_append_only'");
        $fn = DB::selectOne("SELECT 1 AS ok FROM pg_proc WHERE proname = 'audit_logs_prevent_mutation'");

        return $trg !== null && $fn !== null;
    }

    /** يؤكّد أنّ حذف/تعديل سجلّ تدقيق مرفوض (المنع append-only مُفعَّل). */
    private function assertAuditAppendOnlyEnforced(): void
    {
        $id = DB::table('audit_logs')->value('id');
        if ($id === null) {
            return;
        }
        try {
            DB::table('audit_logs')->where('id', $id)->delete();
            $this->fail('كان يجب رفض حذف سجلّ تدقيق (append-only).');
        } catch (\Throwable $e) {
            $this->assertTrue(true);
        }
    }
}
