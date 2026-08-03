<?php

namespace Tests\Feature\Legal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Models\Employee;
use Modules\Legal\Models\CaseAssignment;
use Modules\Legal\Models\CaseDocument;
use Modules\Legal\Models\LegalCase;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    private function lawyerWithCase(string $internal): array
    {
        $user = $this->userWithPermissions(['cases.view_own']);
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $case = LegalCase::factory()->create(['internal_number' => $internal, 'responsible_lawyer_id' => $employee->id]);
        CaseAssignment::create(['case_id' => $case->id, 'employee_id' => $employee->id, 'role' => 'lead']);

        return [$user, $case];
    }

    public function test_can_add_document_metadata(): void
    {
        $uploader = $this->userWithPermissions(['documents.upload', 'cases.view_all']);
        $case = LegalCase::factory()->create();

        $this->actingAsToken($uploader)
            ->postJson("/api/cases/{$case->id}/documents", [
                'title' => 'مذكرة دفاع',
                'document_type' => 'مذكرة',
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'مذكرة دفاع')
            ->assertJsonPath('data.document_type', 'مذكرة');

        $this->assertDatabaseHas('case_documents', [
            'case_id' => $case->id, 'title' => 'مذكرة دفاع', 'uploaded_by' => $uploader->id,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'case_document_added']);
    }

    /**
     * P0 (Phase 5): العميل لا يستطيع تحديد قرص/مسار التخزين — تُهمَل أيّ قيمة يرسلها،
     * فلا يبقى في السجلّ أثر لمسار محقون. الخادم وحده يقرّر القرص والمسار (PR-2).
     */
    public function test_client_cannot_set_storage_path_or_disk(): void
    {
        $uploader = $this->userWithPermissions(['documents.upload', 'cases.view_all']);
        $case = LegalCase::factory()->create();

        $this->actingAsToken($uploader)
            ->postJson("/api/cases/{$case->id}/documents", [
                'title' => 'محاولة حقن',
                'storage_disk' => 'local',
                'storage_path' => '../../../etc/passwd',
            ])
            ->assertCreated();

        $document = CaseDocument::where('case_id', $case->id)->firstOrFail();
        $this->assertNull($document->storage_path);
        $this->assertNull($document->storage_disk);
    }

    public function test_can_delete_document(): void
    {
        $manager = $this->userWithPermissions(['documents.delete', 'cases.view_all']);
        $document = CaseDocument::factory()->create();

        $this->actingAsToken($manager)->deleteJson("/api/documents/{$document->id}")
            ->assertOk();

        $this->assertDatabaseMissing('case_documents', ['id' => $document->id]);
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

    public function test_add_validates_input(): void
    {
        $uploader = $this->userWithPermissions(['documents.upload', 'cases.view_all']);
        $case = LegalCase::factory()->create();

        $this->actingAsToken($uploader)
            ->postJson("/api/cases/{$case->id}/documents", ['title' => ''])
            ->assertStatus(422);
    }

    public function test_requires_authentication(): void
    {
        $case = LegalCase::factory()->create();
        $this->getJson("/api/cases/{$case->id}/documents")->assertStatus(401);
    }
}
