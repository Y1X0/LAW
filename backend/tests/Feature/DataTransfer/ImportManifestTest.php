<?php

namespace Tests\Feature\DataTransfer;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

/**
 * سِجلّ المستوردات (Phase 11 · PR-2): مصدر الحقيقة الوحيد لمركز الهجرة — يعيد فقط الأنواع
 * التي يملك المستخدم صلاحية استيرادها، فلا تكشف الواجهة مستورداً لا يستطيع تنفيذه.
 */
class ImportManifestTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    public function test_manifest_returns_only_permitted_importers(): void
    {
        $actor = $this->userWithPermissions(['cases.create']); // القضايا فقط

        $res = $this->actingAsToken($actor)->getJson('/api/admin/data/import/manifest')
            ->assertOk();

        $keys = collect($res->json('data'))->pluck('key');
        $this->assertTrue($keys->contains('cases'));
        $this->assertFalse($keys->contains('clients'));
        $this->assertFalse($keys->contains('employees'));
    }

    public function test_manifest_lists_all_when_user_has_every_import_permission(): void
    {
        $actor = $this->userWithPermissions(['clients.create', 'cases.create', 'employees.create']);

        $res = $this->actingAsToken($actor)->getJson('/api/admin/data/import/manifest')
            ->assertOk();

        $keys = collect($res->json('data'))->pluck('key')->all();
        $this->assertEqualsCanonicalizing(['clients', 'cases', 'employees'], $keys);
        // العملاء والقضايا يدعمان مطابقة الأعمدة، الموظفون لا.
        $byKey = collect($res->json('data'))->keyBy('key');
        $this->assertTrue($byKey['cases']['mapping']);
        $this->assertFalse($byKey['employees']['mapping']);
    }

    public function test_manifest_is_empty_without_any_import_permission(): void
    {
        $actor = $this->userWithPermissions(['cases.view_all']); // لا صلاحية إنشاء

        $this->actingAsToken($actor)->getJson('/api/admin/data/import/manifest')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_manifest_requires_authentication(): void
    {
        $this->getJson('/api/admin/data/import/manifest')->assertStatus(401);
    }
}
