<?php

namespace Tests\Feature\CustomFields;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CustomFields\Models\CustomFieldDefinition;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

/**
 * تعريفات الحقول المخصّصة (Phase 12 · PR-1): CRUD إداري محميّ بـ custom_fields.manage،
 * تفرّد key لكل كيان، إلزام options للقائمة المنسدلة، صلاحيات كل حقل كقوائم أدوار، وتدقيق.
 * لا يمسّ هذا PR قيم الكيانات (قضايا/عملاء).
 */
class CustomFieldDefinitionTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    private const BASE = '/api/admin/custom-fields';

    private function textFieldPayload(array $overrides = []): array
    {
        return array_merge([
            'entity' => 'case',
            'key' => 'contract_number',
            'label' => 'رقم العقد',
            'type' => 'text',
            'required' => true,
            'display_in' => ['create', 'edit', 'details'],
            'view_roles' => ['admin', 'lawyer'],
            'edit_roles' => ['admin'],
        ], $overrides);
    }

    public function test_defining_a_field_requires_manage_permission(): void
    {
        $viewer = $this->userWithPermissions(['cases.view_all']); // لا يملك custom_fields.manage

        $this->actingAsToken($viewer)->postJson(self::BASE, $this->textFieldPayload())
            ->assertStatus(403);
    }

    public function test_admin_can_define_a_text_field_and_it_is_audited(): void
    {
        $admin = $this->userWithPermissions(['custom_fields.manage']);

        $this->actingAsToken($admin)->postJson(self::BASE, $this->textFieldPayload())
            ->assertStatus(201)
            ->assertJsonPath('data.entity', 'case')
            ->assertJsonPath('data.key', 'contract_number')
            ->assertJsonPath('data.required', true)
            ->assertJsonPath('data.view_roles', ['admin', 'lawyer']);

        $this->assertDatabaseHas('custom_field_definitions', [
            'entity' => 'case', 'key' => 'contract_number', 'type' => 'text',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'custom_field_defined']);
    }

    public function test_dropdown_requires_options(): void
    {
        $admin = $this->userWithPermissions(['custom_fields.manage']);

        $this->actingAsToken($admin)->postJson(self::BASE, $this->textFieldPayload([
            'key' => 'client_grade', 'type' => 'dropdown', 'options' => null,
        ]))->assertStatus(422)->assertJsonPath('errors.fields.options.0', fn ($m) => is_string($m));

        // مع خيارات ⇒ ينجح.
        $this->actingAsToken($admin)->postJson(self::BASE, $this->textFieldPayload([
            'key' => 'client_grade', 'type' => 'dropdown', 'options' => ['VIP', 'عادي'],
        ]))->assertStatus(201)->assertJsonPath('data.options', ['VIP', 'عادي']);
    }

    public function test_key_is_unique_per_entity_but_reusable_across_entities(): void
    {
        $admin = $this->userWithPermissions(['custom_fields.manage']);

        $this->actingAsToken($admin)->postJson(self::BASE, $this->textFieldPayload())->assertStatus(201);

        // نفس المفتاح لنفس الكيان ⇒ 422.
        $this->actingAsToken($admin)->postJson(self::BASE, $this->textFieldPayload())->assertStatus(422);

        // نفس المفتاح لكيان مختلف ⇒ مسموح.
        $this->actingAsToken($admin)->postJson(self::BASE, $this->textFieldPayload(['entity' => 'client']))
            ->assertStatus(201);
    }

    public function test_rejects_invalid_type_entity_and_key_format(): void
    {
        $admin = $this->userWithPermissions(['custom_fields.manage']);

        $this->actingAsToken($admin)->postJson(self::BASE, $this->textFieldPayload(['type' => 'wizardry']))->assertStatus(422);
        $this->actingAsToken($admin)->postJson(self::BASE, $this->textFieldPayload(['entity' => 'invoice']))->assertStatus(422);
        $this->actingAsToken($admin)->postJson(self::BASE, $this->textFieldPayload(['key' => 'Bad Key!']))->assertStatus(422);
        $this->actingAsToken($admin)->postJson(self::BASE, $this->textFieldPayload(['view_roles' => ['ghost']]))->assertStatus(422);
    }

    public function test_list_filters_by_entity_and_orders_by_sort_order(): void
    {
        $admin = $this->userWithPermissions(['custom_fields.manage']);
        $this->actingAsToken($admin)->postJson(self::BASE, $this->textFieldPayload(['key' => 'b_field', 'sort_order' => 2]))->assertStatus(201);
        $this->actingAsToken($admin)->postJson(self::BASE, $this->textFieldPayload(['key' => 'a_field', 'sort_order' => 1]))->assertStatus(201);
        $this->actingAsToken($admin)->postJson(self::BASE, $this->textFieldPayload(['entity' => 'client', 'key' => 'c_field']))->assertStatus(201);

        $res = $this->actingAsToken($admin)->getJson(self::BASE.'?entity=case')->assertOk();
        $keys = collect($res->json('data'))->pluck('key')->all();
        $this->assertSame(['a_field', 'b_field'], $keys); // مرتّب بـ sort_order، وكيان case فقط
    }

    public function test_update_changes_attributes_is_audited_and_ignores_entity_key(): void
    {
        $admin = $this->userWithPermissions(['custom_fields.manage']);
        $id = $this->actingAsToken($admin)->postJson(self::BASE, $this->textFieldPayload())->json('data.id');

        $this->actingAsToken($admin)->patchJson(self::BASE."/{$id}", [
            'label' => 'رقم العقد الداخلي', 'edit_roles' => ['admin', 'secretary'],
            'entity' => 'client', 'key' => 'hacked', // يجب تجاهلهما
        ])->assertOk()->assertJsonPath('data.label', 'رقم العقد الداخلي')
            ->assertJsonPath('data.edit_roles', ['admin', 'secretary']);

        $this->assertDatabaseHas('custom_field_definitions', [
            'id' => $id, 'entity' => 'case', 'key' => 'contract_number', 'label' => 'رقم العقد الداخلي',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'custom_field_updated']);
    }

    public function test_delete_removes_and_is_audited(): void
    {
        $admin = $this->userWithPermissions(['custom_fields.manage']);
        $id = $this->actingAsToken($admin)->postJson(self::BASE, $this->textFieldPayload())->json('data.id');

        $this->actingAsToken($admin)->deleteJson(self::BASE."/{$id}")->assertOk();

        $this->assertDatabaseMissing('custom_field_definitions', ['id' => $id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'custom_field_deleted']);
    }

    public function test_definition_endpoints_require_authentication(): void
    {
        $this->getJson(self::BASE)->assertStatus(401);
    }

    public function test_model_exposes_type_and_context_catalogs(): void
    {
        $this->assertContains('dropdown', CustomFieldDefinition::TYPES);
        $this->assertContains('list', CustomFieldDefinition::CONTEXTS);
        $this->assertContains('case', CustomFieldDefinition::ENTITIES);
    }
}
