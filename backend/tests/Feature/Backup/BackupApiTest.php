<?php

namespace Tests\Feature\Backup;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\Backup\Contracts\DatabaseDumper;
use Modules\Backup\Models\Backup;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

/**
 * إدارة النسخ عبر API (Phase 13 · PR-2): محميّ بـ backup.manage — قائمة/إنشاء/تنزيل، مع تدقيق.
 * لا استعادة عبر API. يُستبدَل مُفرِّغ القاعدة بمُفرِّغ وهمي (لا pg_dump حقيقي في الاختبار).
 */
class BackupApiTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    private function fakeDumper(): void
    {
        $this->app->instance(DatabaseDumper::class, new class implements DatabaseDumper
        {
            public function dump(string $targetPath): void
            {
                file_put_contents($targetPath, 'DUMP-BYTES');
            }
        });
    }

    public function test_backup_endpoints_require_manage_permission(): void
    {
        $viewer = $this->userWithPermissions(['cases.view_all']);

        $this->actingAsToken($viewer)->getJson('/api/admin/backups')->assertStatus(403);
        $this->actingAsToken($viewer)->postJson('/api/admin/backups')->assertStatus(403);
    }

    public function test_owner_creates_a_backup_now_and_it_is_audited(): void
    {
        Storage::fake('backups');
        $this->fakeDumper();
        $owner = $this->userWithPermissions(['backup.manage']);

        $res = $this->actingAsToken($owner)->postJson('/api/admin/backups')
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.kind', 'manual')
            ->assertJsonPath('data.trigger', 'manual');

        $id = $res->json('data.id');
        Storage::disk('backups')->assertExists(Backup::find($id)->path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'backup_created', 'auditable_id' => $id]);
    }

    public function test_index_lists_backups_newest_first(): void
    {
        $owner = $this->userWithPermissions(['backup.manage']);
        Backup::create(['disk' => 'backups', 'filename' => 'a.dump', 'kind' => 'daily', 'status' => 'completed', 'trigger' => 'scheduled', 'size_bytes' => 10]);
        Backup::create(['disk' => 'backups', 'filename' => 'b.dump', 'kind' => 'manual', 'status' => 'completed', 'trigger' => 'manual', 'size_bytes' => 20]);

        $data = $this->actingAsToken($owner)->getJson('/api/admin/backups')->assertOk()->json('data');
        $this->assertSame('b.dump', $data[0]['filename']); // الأحدث أولاً
        $this->assertSame(20, $data[0]['size_bytes']);
    }

    public function test_download_streams_completed_backup_and_audits(): void
    {
        Storage::fake('backups');
        $owner = $this->userWithPermissions(['backup.manage']);
        Storage::disk('backups')->put('db/x.dump', 'DUMP-BYTES');
        $backup = Backup::create(['disk' => 'backups', 'path' => 'db/x.dump', 'filename' => 'x.dump', 'kind' => 'manual', 'status' => 'completed', 'trigger' => 'manual', 'size_bytes' => 10]);

        $this->actingAsToken($owner)->get("/api/admin/backups/{$backup->id}/download")->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'backup_downloaded', 'auditable_id' => $backup->id]);
    }

    public function test_download_of_incomplete_backup_is_404(): void
    {
        $owner = $this->userWithPermissions(['backup.manage']);
        $backup = Backup::create(['disk' => 'backups', 'kind' => 'daily', 'status' => 'failed', 'trigger' => 'scheduled']);

        $this->actingAsToken($owner)->get("/api/admin/backups/{$backup->id}/download")->assertStatus(404);
    }

    public function test_endpoints_require_authentication(): void
    {
        $this->getJson('/api/admin/backups')->assertStatus(401);
    }
}
