<?php

namespace Tests\Feature\Backup;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\Backup\Contracts\DatabaseDumper;
use Modules\Backup\Models\Backup;
use Modules\Backup\Services\BackupService;
use Tests\TestCase;

/**
 * خدمة النسخ الاحتياطي (Phase 13 · PR-1): التفريغ (مُفرِّغ وهمي) → الرفع لقرص backups → تسجيل
 * وتدقيق → تقليم الاحتفاظ المتدرّج. لا يشغّل pg_dump حقيقياً (يُتحقَّق منه حيّاً عند الاستعادة).
 */
class BackupServiceTest extends TestCase
{
    use RefreshDatabase;

    private function fakeDumper(string $bytes = 'PGDUMP-DATA'): void
    {
        $this->app->instance(DatabaseDumper::class, new class($bytes) implements DatabaseDumper
        {
            public function __construct(private string $bytes) {}

            public function dump(string $targetPath): void
            {
                file_put_contents($targetPath, $this->bytes);
            }
        });
    }

    public function test_run_creates_uploads_records_and_audits(): void
    {
        Storage::fake('backups');
        $this->fakeDumper('HELLO-DUMP');
        $owner = User::factory()->create();

        $backup = app(BackupService::class)->run('manual', 'manual', $owner->id);

        $this->assertSame('completed', $backup->status);
        $this->assertSame('manual', $backup->kind);
        $this->assertSame(10, $backup->size_bytes); // strlen('HELLO-DUMP')
        Storage::disk('backups')->assertExists($backup->path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'backup_created', 'auditable_id' => $backup->id]);
    }

    public function test_run_records_failure_and_rethrows(): void
    {
        Storage::fake('backups');
        $this->app->instance(DatabaseDumper::class, new class implements DatabaseDumper
        {
            public function dump(string $targetPath): void
            {
                throw new \RuntimeException('pg_dump غير متاح');
            }
        });

        try {
            app(BackupService::class)->run('daily', 'scheduled');
            $this->fail('كان يجب أن يُعاد رمي الاستثناء.');
        } catch (\RuntimeException) {
            // متوقّع
        }

        $this->assertDatabaseHas('backups', ['kind' => 'daily', 'status' => 'failed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'backup_failed']);
    }

    public function test_retention_prunes_oldest_beyond_keep_per_kind(): void
    {
        Storage::fake('backups');
        $this->fakeDumper();

        // 33 نسخة يومية مكتملة (الحدّ 30) — تُنشأ كصفوف + ملفات.
        for ($i = 1; $i <= 33; $i++) {
            $path = "db/daily-{$i}.dump";
            Storage::disk('backups')->put($path, 'x');
            Backup::create(['disk' => 'backups', 'path' => $path, 'filename' => "daily-{$i}.dump", 'kind' => 'daily', 'status' => 'completed', 'trigger' => 'scheduled', 'size_bytes' => 1]);
        }

        $deleted = app(BackupService::class)->prune();

        $this->assertSame(3, $deleted);
        $this->assertSame(30, Backup::where('kind', 'daily')->count());
        // أقدم 3 (الملفات والصفوف) حُذفت.
        Storage::disk('backups')->assertMissing('db/daily-1.dump');
        Storage::disk('backups')->assertExists('db/daily-33.dump');
        $this->assertDatabaseMissing('backups', ['filename' => 'daily-1.dump']);
    }

    public function test_run_prunes_within_the_same_call(): void
    {
        Storage::fake('backups');
        $this->fakeDumper();

        // املأ الحدّ اليومي (30) ثم أنشئ واحدة جديدة ⇒ تبقى 30 (حُذفت الأقدم).
        for ($i = 1; $i <= 30; $i++) {
            Backup::create(['disk' => 'backups', 'path' => "db/old-{$i}.dump", 'filename' => "old-{$i}.dump", 'kind' => 'daily', 'status' => 'completed', 'trigger' => 'scheduled', 'size_bytes' => 1]);
            Storage::disk('backups')->put("db/old-{$i}.dump", 'x');
        }

        app(BackupService::class)->run('daily', 'scheduled');

        $this->assertSame(30, Backup::where('kind', 'daily')->where('status', 'completed')->count());
        Storage::disk('backups')->assertMissing('db/old-1.dump');
    }
}
