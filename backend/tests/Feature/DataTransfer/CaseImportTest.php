<?php

namespace Tests\Feature\DataTransfer;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Modules\HR\Models\Employee;
use Modules\Legal\Models\Client;
use Modules\Legal\Models\LegalCase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

/**
 * استيراد القضايا من Excel (Phase 11 · PR-1): معاينة بلا حفظ + حفظ ذرّي عبر CaseService
 * (تدقيق + إسناد lead + خط زمني «تم استيراد القضية من النظام السابق») + upsert بمفتاح
 * internal_number + ربط العميل (هوية ثم اسم) والمحامي (رقم موظف ثم هوية ثم اسم) بلا تخمين.
 */
class CaseImportTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    private const HEADERS = [
        'internal_number', 'title', 'client_national_id', 'client_name',
        'lawyer_employee_no', 'status', 'opened_date', 'value',
    ];

    private function xlsx(array $rows): UploadedFile
    {
        return $this->xlsxWith(self::HEADERS, $rows);
    }

    private function xlsxWith(array $headers, array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($headers, null, 'A1');
        if ($rows !== []) {
            $sheet->fromArray($rows, null, 'A2');
        }
        $path = tempnam(sys_get_temp_dir(), 'kimp').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'cases.xlsx', null, null, true);
    }

    private function client(string $nationalId, string $name = 'عميل'): Client
    {
        return Client::create(['name' => $name, 'type' => 'individual', 'national_id' => $nationalId, 'status' => 'active']);
    }

    public function test_preview_reports_create_and_update_without_saving(): void
    {
        $client = $this->client('111');
        LegalCase::factory()->create(['internal_number' => 'C-1', 'client_id' => $client->id]);
        $actor = $this->userWithPermissions(['cases.create']);

        $file = $this->xlsx([
            ['C-1', 'قضية محدّثة', '111', '', '', 'open', '', ''],   // تحديث (internal_number موجود)
            ['C-2', 'قضية جديدة', '111', '', '', 'open', '', ''],    // إنشاء
        ]);

        $this->actingAsToken($actor)->post('/api/admin/data/import/cases/preview', ['file' => $file])
            ->assertOk()
            ->assertJsonPath('data.create', 1)
            ->assertJsonPath('data.update', 1)
            ->assertJsonPath('data.invalid', 0);

        $this->assertDatabaseMissing('cases', ['internal_number' => 'C-2']); // معاينة لا تحفظ
    }

    public function test_commit_creates_via_case_service_with_audit_timeline_and_assignment(): void
    {
        $client = $this->client('111');
        $lawyer = Employee::factory()->create(['employee_no' => 'EMP-1']);
        $actor = $this->userWithPermissions(['cases.create']);

        $file = $this->xlsx([
            ['C-10', 'قضية أولى', '111', '', 'EMP-1', 'open', '2020-03-01', '5000'],
        ]);

        $this->actingAsToken($actor)->post('/api/admin/data/import/cases/commit', ['file' => $file])
            ->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.updated', 0);

        $case = LegalCase::where('internal_number', 'C-10')->first();
        $this->assertNotNull($case);
        $this->assertSame($client->id, $case->client_id);
        $this->assertSame($lawyer->id, $case->responsible_lawyer_id);
        // مرّ عبر CaseService: إسناد lead + خط زمني + تدقيق دفعة + تدقيق إنشاء.
        $this->assertDatabaseHas('case_assignments', ['case_id' => $case->id, 'employee_id' => $lawyer->id, 'role' => 'lead']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'cases_imported']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'case_created']);
    }

    public function test_imported_case_records_import_timeline_event_not_created(): void
    {
        $this->client('111');
        $actor = $this->userWithPermissions(['cases.create']);
        $file = $this->xlsx([['C-20', 'قضية قديمة', '111', '', '', 'open', '2019-01-01', '']]);

        $this->actingAsToken($actor)->post('/api/admin/data/import/cases/commit', ['file' => $file])->assertOk();

        $case = LegalCase::where('internal_number', 'C-20')->first();
        // خط زمني صادق: حدث استيراد لا «تم إنشاء القضية»؛ opened_date يبقى التاريخ القانوني.
        $this->assertDatabaseHas('case_timeline_events', [
            'case_id' => $case->id,
            'event_type' => 'case_imported',
            'title' => 'تم استيراد القضية من النظام السابق',
        ]);
        $this->assertDatabaseMissing('case_timeline_events', ['case_id' => $case->id, 'event_type' => 'case_created']);
        $this->assertSame('2019-01-01', $case->opened_date->format('Y-m-d'));
    }

    public function test_commit_is_atomic_when_a_row_is_invalid(): void
    {
        $this->client('111');
        $actor = $this->userWithPermissions(['cases.create']);

        $file = $this->xlsx([
            ['C-30', 'صحيحة', '111', '', '', 'open', '', ''],
            ['C-31', 'حالة خاطئة', '111', '', '', 'bad_status', '', ''], // status خارج STATUSES
        ]);

        $this->actingAsToken($actor)->post('/api/admin/data/import/cases/commit', ['file' => $file])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'IMPORT_VALIDATION');

        // الكل-أو-لا-شيء: حتى الصف الصحيح لم يُحفَظ.
        $this->assertDatabaseMissing('cases', ['internal_number' => 'C-30']);
        $this->assertDatabaseMissing('cases', ['internal_number' => 'C-31']);
    }

    public function test_import_requires_cases_create_permission(): void
    {
        $this->client('111');
        $viewer = $this->userWithPermissions(['cases.view_all']); // لا يملك create
        $file = $this->xlsx([['C-40', 'قضية', '111', '', '', 'open', '', '']]);

        $this->actingAsToken($viewer)->post('/api/admin/data/import/cases/commit', ['file' => $file])
            ->assertStatus(403);
    }

    public function test_client_linked_by_name_errors_on_zero_or_duplicate(): void
    {
        $this->client('111', 'عميل مكرّر');
        $this->client('222', 'عميل مكرّر'); // نفس الاسم — تكرار
        $actor = $this->userWithPermissions(['cases.create']);

        // اسم مكرّر ⇒ خطأ (لا تخمين)، واسم غير موجود ⇒ خطأ.
        $file = $this->xlsx([
            ['C-50', 'قضية', '', 'عميل مكرّر', '', 'open', '', ''],
            ['C-51', 'قضية', '', 'عميل مجهول', '', 'open', '', ''],
        ]);

        $this->actingAsToken($actor)->post('/api/admin/data/import/cases/preview', ['file' => $file])
            ->assertOk()
            ->assertJsonPath('data.invalid', 2);
    }

    public function test_lawyer_empty_is_allowed_but_unknown_is_rejected(): void
    {
        $this->client('111');
        $actor = $this->userWithPermissions(['cases.create']);

        $file = $this->xlsx([
            ['C-60', 'بلا محامٍ', '111', '', '', 'open', '', ''],        // فارغ ⇒ صحيح
            ['C-61', 'محامٍ مجهول', '111', '', 'EMP-XXX', 'open', '', ''], // غير موجود ⇒ خطأ
        ]);

        $this->actingAsToken($actor)->post('/api/admin/data/import/cases/commit', ['file' => $file])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'IMPORT_VALIDATION');

        // الصف الأول صحيح لكنّه لم يُحفَظ (ذرّي).
        $this->assertDatabaseMissing('cases', ['internal_number' => 'C-60']);
    }

    public function test_column_mapping_remaps_arabic_headers(): void
    {
        $this->client('111');
        $actor = $this->userWithPermissions(['cases.create']);
        $file = $this->xlsxWith(['الرقم', 'العنوان', 'هوية العميل'], [
            ['C-70', 'قضية معرّبة', '111'],
        ]);

        $this->actingAsToken($actor)->post('/api/admin/data/import/cases/commit', [
            'file' => $file,
            'mapping' => ['internal_number' => 'الرقم', 'title' => 'العنوان', 'client_national_id' => 'هوية العميل'],
        ])->assertOk()->assertJsonPath('data.created', 1);

        $this->assertDatabaseHas('cases', ['internal_number' => 'C-70', 'title' => 'قضية معرّبة']);
    }

    public function test_preview_returns_fields_match_key_and_detected_headers(): void
    {
        $actor = $this->userWithPermissions(['cases.create']);
        $file = $this->xlsxWith(['الرقم', 'العنوان'], [['C-80', 'قضية']]);

        $res = $this->actingAsToken($actor)->post('/api/admin/data/import/cases/preview', ['file' => $file])
            ->assertOk()
            ->assertJsonPath('data.detected_headers', ['الرقم', 'العنوان'])
            ->assertJsonPath('data.match_keys', ['internal_number']);

        $fields = collect($res->json('data.fields'));
        $this->assertTrue($fields->firstWhere('key', 'internal_number')['required']);
        $this->assertTrue($fields->firstWhere('key', 'title')['required']);
        $this->assertFalse($fields->firstWhere('key', 'court_name')['required']);
    }

    public function test_duplicate_internal_number_in_file_is_rejected(): void
    {
        $this->client('111');
        $actor = $this->userWithPermissions(['cases.create']);
        $file = $this->xlsx([
            ['C-90', 'أولى', '111', '', '', 'open', '', ''],
            ['C-90', 'مكرّرة', '111', '', '', 'open', '', ''], // نفس internal_number في الملف
        ]);

        $this->actingAsToken($actor)->post('/api/admin/data/import/cases/commit', ['file' => $file])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'IMPORT_VALIDATION');

        $this->assertDatabaseMissing('cases', ['internal_number' => 'C-90']);
    }
}
