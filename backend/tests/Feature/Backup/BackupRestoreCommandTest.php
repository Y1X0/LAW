<?php

namespace Tests\Feature\Backup;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Modules\Backup\Contracts\DatabaseRestorer;
use Modules\Backup\Models\Backup;
use Tests\TestCase;

/**
 * أمر الاستعادة backup:restore (Phase 13 · PR-4): مُحصَّن (يتطلّب --force)، يستعيد عبر مُستعيد
 * مُجرَّد (وهمي هنا؛ pg_restore حقيقي في اختبار الاستعادة الحيّ)، ويُدقّق backup_restored.
 */
class BackupRestoreCommandTest extends TestCase
{
    use RefreshDatabase;

    private object $restorer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->restorer = new class implements DatabaseRestorer
        {
            public bool $called = false;

            public ?string $path = null;

            public function restore(string $sourcePath): void
            {
                $this->called = true;
                $this->path = $sourcePath;
            }
        };
        $this->app->instance(DatabaseRestorer::class, $this->restorer);
    }

    private function completedBackup(): Backup
    {
        Storage::fake('backups');
        Storage::disk('backups')->put('db/x.dump', 'DUMP-BYTES');

        return Backup::create(['disk' => 'backups', 'path' => 'db/x.dump', 'filename' => 'x.dump', 'kind' => 'manual', 'status' => 'completed', 'trigger' => 'manual', 'size_bytes' => 10]);
    }

    public function test_restore_with_force_runs_restorer_and_audits(): void
    {
        $backup = $this->completedBackup();

        $code = Artisan::call('backup:restore', ['backup' => $backup->id, '--force' => true]);

        $this->assertSame(0, $code);
        $this->assertTrue($this->restorer->called);
        $this->assertDatabaseHas('audit_logs', ['action' => 'backup_restored', 'auditable_id' => $backup->id]);
    }

    public function test_restore_of_missing_backup_fails(): void
    {
        $code = Artisan::call('backup:restore', ['backup' => 9999, '--force' => true]);

        $this->assertSame(1, $code);
        $this->assertFalse($this->restorer->called);
    }

    public function test_restore_of_incomplete_backup_fails(): void
    {
        $backup = Backup::create(['disk' => 'backups', 'kind' => 'daily', 'status' => 'failed', 'trigger' => 'scheduled']);

        $code = Artisan::call('backup:restore', ['backup' => $backup->id, '--force' => true]);

        $this->assertSame(1, $code);
        $this->assertFalse($this->restorer->called);
    }
}
