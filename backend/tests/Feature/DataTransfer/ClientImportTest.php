<?php

namespace Tests\Feature\DataTransfer;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Modules\Legal\Models\Client;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

/**
 * استيراد العملاء من Excel (Phase 10 · PR-1): معاينة بلا حفظ + حفظ ذرّي عبر ClientService
 * + upsert بمفتاح national_id + تحقّق (قواعد StoreClientRequest) + صلاحية clients.create.
 */
class ClientImportTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    private const HEADERS = ['name', 'type', 'phone', 'email', 'national_id', 'status'];

    private function xlsx(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(self::HEADERS, null, 'A1');
        if ($rows !== []) {
            $sheet->fromArray($rows, null, 'A2');
        }
        $path = tempnam(sys_get_temp_dir(), 'cimp').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'clients.xlsx', null, null, true);
    }

    public function test_preview_reports_create_and_update_without_saving(): void
    {
        Client::create(['name' => 'عميل قائم', 'type' => 'individual', 'national_id' => '111', 'status' => 'active']);
        $actor = $this->userWithPermissions(['clients.create']);

        $file = $this->xlsx([
            ['عميل محدّث', 'individual', '079', 'a@x.com', '111', 'active'], // تحديث (national_id موجود)
            ['شركة جديدة', 'company', '078', 'b@x.com', '222', 'active'],    // إنشاء
        ]);

        $this->actingAsToken($actor)->post('/api/admin/data/import/clients/preview', ['file' => $file])
            ->assertOk()
            ->assertJsonPath('data.create', 1)
            ->assertJsonPath('data.update', 1)
            ->assertJsonPath('data.invalid', 0);

        $this->assertDatabaseMissing('clients', ['national_id' => '222']); // معاينة لا تحفظ
    }

    public function test_commit_creates_and_updates_via_service(): void
    {
        Client::create(['name' => 'اسم قديم', 'type' => 'individual', 'national_id' => '111', 'status' => 'active']);
        $actor = $this->userWithPermissions(['clients.create']);

        $file = $this->xlsx([
            ['اسم محدّث', 'individual', '079', 'a@x.com', '111', 'inactive'],
            ['شركة جديدة', 'company', '078', 'b@x.com', '222', 'active'],
        ]);

        $this->actingAsToken($actor)->post('/api/admin/data/import/clients/commit', ['file' => $file])
            ->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.updated', 1);

        $this->assertDatabaseHas('clients', ['national_id' => '111', 'name' => 'اسم محدّث', 'status' => 'inactive']);
        $this->assertDatabaseHas('clients', ['national_id' => '222', 'name' => 'شركة جديدة', 'type' => 'company']);
        // مرّ عبر ClientService → تدقيق لكل صف + دفعة.
        $this->assertDatabaseHas('audit_logs', ['action' => 'clients_imported']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'client_created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'client_updated']);
    }

    public function test_commit_is_atomic_when_a_row_is_invalid(): void
    {
        $actor = $this->userWithPermissions(['clients.create']);

        $file = $this->xlsx([
            ['عميل صحيح', 'individual', '079', '', '222', 'active'],
            ['نوع خاطئ', 'bad_type', '078', '', '333', 'active'], // type خارج TYPES
        ]);

        $this->actingAsToken($actor)->post('/api/admin/data/import/clients/commit', ['file' => $file])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'IMPORT_VALIDATION');

        // الكل-أو-لا-شيء: حتى الصف الصحيح لم يُحفَظ.
        $this->assertDatabaseMissing('clients', ['national_id' => '222']);
        $this->assertDatabaseMissing('clients', ['national_id' => '333']);
    }

    public function test_import_requires_clients_create_permission(): void
    {
        $viewer = $this->userWithPermissions(['clients.view']); // لا يملك create
        $file = $this->xlsx([['عميل', 'individual', '079', '', '999', 'active']]);

        $this->actingAsToken($viewer)->post('/api/admin/data/import/clients/commit', ['file' => $file])
            ->assertStatus(403);
    }
}
