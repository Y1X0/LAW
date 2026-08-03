<?php

namespace Tests\Feature\Legal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\HR\Models\Employee;
use Modules\Legal\Models\CaseAssignment;
use Modules\Legal\Models\CaseDocument;
use Modules\Legal\Models\LegalCase;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('r2'); // لا اتصال فعلي بـ R2 في الاختبارات.
    }

    private function lawyerWithCase(string $internal): array
    {
        $user = $this->userWithPermissions(['cases.view_own']);
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $case = LegalCase::factory()->create(['internal_number' => $internal, 'responsible_lawyer_id' => $employee->id]);
        CaseAssignment::create(['case_id' => $case->id, 'employee_id' => $employee->id, 'role' => 'lead']);

        return [$user, $case];
    }

    public function test_can_upload_document_to_storage(): void
    {
        $uploader = $this->userWithPermissions(['documents.upload', 'cases.view_all']);
        $case = LegalCase::factory()->create();
        $file = UploadedFile::fake()->create('مذكرة دفاع.pdf', 120, 'application/pdf');

        $this->actingAsToken($uploader)
            ->post("/api/cases/{$case->id}/documents", ['title' => 'مذكرة دفاع', 'document_type' => 'مذكرة', 'file' => $file])
            ->assertCreated()
            ->assertJsonPath('data.title', 'مذكرة دفاع')
            ->assertJsonPath('data.original_name', 'مذكرة دفاع.pdf');

        $document = CaseDocument::where('case_id', $case->id)->firstOrFail();
        $this->assertSame('r2', $document->storage_disk);
        $this->assertStringStartsWith("cases/{$case->id}/", $document->storage_path);
        $this->assertSame('application/pdf', $document->mime_type);
        $this->assertNotNull($document->checksum);
        $this->assertSame(120 * 1024, $document->size_bytes);
        Storage::disk('r2')->assertExists($document->storage_path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'case_document_added']);
    }

    /** P0: العميل لا يتحكّم بالمسار — يُشتقّ خادميّاً مهما أرسل. */
    public function test_client_cannot_control_storage_path(): void
    {
        $uploader = $this->userWithPermissions(['documents.upload', 'cases.view_all']);
        $case = LegalCase::factory()->create();

        $this->actingAsToken($uploader)->post("/api/cases/{$case->id}/documents", [
            'title' => 'حقن',
            'storage_disk' => 'local',
            'storage_path' => '../../../etc/passwd',
            'file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
        ])->assertCreated();

        $document = CaseDocument::where('case_id', $case->id)->firstOrFail();
        $this->assertSame('r2', $document->storage_disk);
        $this->assertStringStartsWith("cases/{$case->id}/", $document->storage_path);
        $this->assertStringNotContainsString('..', $document->storage_path);
    }

    public function test_rejects_disallowed_file_type(): void
    {
        $uploader = $this->userWithPermissions(['documents.upload', 'cases.view_all']);
        $case = LegalCase::factory()->create();

        $this->actingAsToken($uploader)->post("/api/cases/{$case->id}/documents", [
            'title' => 'خبيث', 'file' => UploadedFile::fake()->create('evil.exe', 10),
        ])->assertStatus(422)->assertJsonPath('errors.code', 'VALIDATION_ERROR');
    }

    public function test_rejects_oversize_file(): void
    {
        $uploader = $this->userWithPermissions(['documents.upload', 'cases.view_all']);
        $case = LegalCase::factory()->create();

        $this->actingAsToken($uploader)->post("/api/cases/{$case->id}/documents", [
            'title' => 'كبير', 'file' => UploadedFile::fake()->create('big.pdf', 21000, 'application/pdf'),
        ])->assertStatus(422)->assertJsonPath('errors.code', 'VALIDATION_ERROR');
    }

    public function test_upload_requires_a_file(): void
    {
        $uploader = $this->userWithPermissions(['documents.upload', 'cases.view_all']);
        $case = LegalCase::factory()->create();

        $this->actingAsToken($uploader)
            ->postJson("/api/cases/{$case->id}/documents", ['title' => 'بلا ملف'])
            ->assertStatus(422)->assertJsonPath('errors.code', 'VALIDATION_ERROR');
    }

    public function test_can_download_own_case_document(): void
    {
        [$user, $case] = $this->lawyerWithCase('DL-1');
        $uploader = $this->userWithPermissions(['documents.upload', 'cases.view_all']);
        $this->actingAsToken($uploader)->post("/api/cases/{$case->id}/documents", [
            'title' => 'ملف', 'file' => UploadedFile::fake()->create('doc.pdf', 20, 'application/pdf'),
        ])->assertCreated();
        $document = CaseDocument::where('case_id', $case->id)->firstOrFail();

        $this->actingAsToken($user)->get("/api/documents/{$document->id}/download")
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_download_isolated_by_case(): void
    {
        [$userA] = $this->lawyerWithCase('A-D');
        [, $caseB] = $this->lawyerWithCase('B-D');
        $uploader = $this->userWithPermissions(['documents.upload', 'cases.view_all']);
        $this->actingAsToken($uploader)->post("/api/cases/{$caseB->id}/documents", [
            'title' => 'خاص B', 'file' => UploadedFile::fake()->create('b.pdf', 10, 'application/pdf'),
        ])->assertCreated();
        $document = CaseDocument::where('case_id', $caseB->id)->firstOrFail();

        $this->actingAsToken($userA)->getJson("/api/documents/{$document->id}/download")
            ->assertStatus(403)->assertJsonPath('errors.code', 'FORBIDDEN');
    }

    public function test_download_of_metadata_only_document_returns_404(): void
    {
        $viewer = $this->userWithPermissions(['cases.view_all']);
        $document = CaseDocument::factory()->create(['storage_path' => null, 'storage_disk' => null]);

        $this->actingAsToken($viewer)->getJson("/api/documents/{$document->id}/download")
            ->assertStatus(404)->assertJsonPath('errors.code', 'NO_FILE');
    }

    public function test_delete_removes_file_and_row(): void
    {
        $manager = $this->userWithPermissions(['documents.upload', 'documents.delete', 'cases.view_all']);
        $case = LegalCase::factory()->create();
        $this->actingAsToken($manager)->post("/api/cases/{$case->id}/documents", [
            'title' => 'للحذف', 'file' => UploadedFile::fake()->create('del.pdf', 10, 'application/pdf'),
        ])->assertCreated();
        $document = CaseDocument::where('case_id', $case->id)->firstOrFail();
        Storage::disk('r2')->assertExists($document->storage_path);

        $this->actingAsToken($manager)->deleteJson("/api/documents/{$document->id}")->assertOk();

        $this->assertDatabaseMissing('case_documents', ['id' => $document->id]);
        Storage::disk('r2')->assertMissing($document->storage_path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'case_document_deleted']);
    }

    public function test_lawyer_sees_only_own_case_documents(): void
    {
        [$userA, $caseA] = $this->lawyerWithCase('A-2');
        [, $caseB] = $this->lawyerWithCase('B-2');
        CaseDocument::factory()->create(['case_id' => $caseA->id, 'title' => 'مستند A']);
        CaseDocument::factory()->create(['case_id' => $caseB->id, 'title' => 'مستند B']);

        $this->actingAsToken($userA)->getJson("/api/cases/{$caseA->id}/documents")
            ->assertOk()
            ->assertJsonPath('data.0.title', 'مستند A');

        $this->actingAsToken($userA)->getJson("/api/cases/{$caseB->id}/documents")
            ->assertStatus(403)
            ->assertJsonPath('errors.code', 'FORBIDDEN');
    }

    public function test_add_requires_upload_permission(): void
    {
        $viewer = $this->userWithPermissions(['cases.view_all']);
        $case = LegalCase::factory()->create();

        $this->actingAsToken($viewer)
            ->postJson("/api/cases/{$case->id}/documents", ['title' => 'x'])
            ->assertStatus(403);
    }

    public function test_delete_requires_delete_permission(): void
    {
        $uploader = $this->userWithPermissions(['documents.upload', 'cases.view_all']);
        $document = CaseDocument::factory()->create();

        $this->actingAsToken($uploader)->deleteJson("/api/documents/{$document->id}")
            ->assertStatus(403);
        $this->assertDatabaseHas('case_documents', ['id' => $document->id]);
    }

    public function test_requires_authentication(): void
    {
        $case = LegalCase::factory()->create();
        $this->getJson("/api/cases/{$case->id}/documents")->assertStatus(401);
    }
}
