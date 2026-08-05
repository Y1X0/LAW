<?php

namespace Tests\Feature\CustomFields;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Core\Models\AuditLog;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\CustomFields\Models\CustomFieldDefinition;
use Modules\HR\Models\Employee;
use Modules\Legal\Models\Client;
use Modules\Legal\Models\LegalCase;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

/**
 * محرّك قيم الحقول المخصّصة (Phase 12 · PR-3): قراءة مصفّاة بـ view_roles بعد عزل القضية،
 * كتابة تفرض edit_roles (تغيير غير مصرّح ⇒ 422 ذرّي يسمّي الحقل)، وتدقيق كل تغيّر (old→new).
 */
class CustomFieldValuesTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    private function userWithRole(string $roleName, array $permissions): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => $roleName]);
        foreach ($permissions as $name) {
            $perm = Permission::firstOrCreate(['name' => $name], ['module' => Str::before($name, '.')]);
            $role->permissions()->syncWithoutDetaching($perm->id);
        }
        $user->assignRole($role);

        return $user;
    }

    private function client(): Client
    {
        return Client::create(['name' => 'عميل', 'type' => 'individual', 'status' => 'active']);
    }

    private function defineField(array $attrs): CustomFieldDefinition
    {
        return CustomFieldDefinition::create(array_merge([
            'entity' => 'case', 'label' => 'حقل', 'type' => 'text', 'required' => false,
            'display_in' => ['create', 'edit', 'details', 'list'], 'is_active' => true, 'sort_order' => 1,
        ], $attrs));
    }

    public function test_create_case_with_custom_value_persists_and_audits(): void
    {
        $owner = $this->userWithRole('owner', ['custom_fields.manage', 'cases.create', 'cases.view_all']);
        $this->defineField(['key' => 'contract_number', 'type' => 'text']);
        $client = $this->client();

        $this->actingAsToken($owner)->postJson('/api/cases', [
            'internal_number' => 'K-1', 'title' => 'قضية', 'client_id' => $client->id,
            'custom_fields' => ['contract_number' => 'C-123'],
        ])->assertStatus(201);

        $case = LegalCase::where('internal_number', 'K-1')->first();
        $this->assertDatabaseHas('custom_field_values', ['entity' => 'case', 'entity_id' => $case->id, 'value_text' => 'C-123']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'custom_field_value_changed', 'auditable_id' => $case->id]);
    }

    public function test_update_audits_old_to_new_value(): void
    {
        $owner = $this->userWithRole('owner', ['custom_fields.manage', 'cases.create', 'cases.update', 'cases.view_all']);
        $this->defineField(['key' => 'contract_value', 'type' => 'currency']);
        $client = $this->client();

        $id = $this->actingAsToken($owner)->postJson('/api/cases', [
            'internal_number' => 'K-2', 'title' => 'قضية', 'client_id' => $client->id,
            'custom_fields' => ['contract_value' => 10000],
        ])->json('data.id');

        $this->actingAsToken($owner)->putJson("/api/cases/{$id}", [
            'custom_fields' => ['contract_value' => 50000],
        ])->assertOk();

        $this->assertDatabaseHas('custom_field_values', ['entity_id' => $id, 'value_number' => 50000]);
        // تدقيق قانوني: القيمة قبل/بعد.
        $log = AuditLog::where('action', 'custom_field_value_changed')
            ->where('auditable_id', $id)->latest('id')->first();
        $this->assertSame(10000.0, (float) $log->old_values['old']);
        $this->assertSame(50000.0, (float) $log->new_values['new']);
    }

    public function test_owner_sees_restricted_field_but_lawyer_does_not(): void
    {
        $owner = $this->userWithRole('owner', ['custom_fields.manage', 'cases.create', 'cases.view_all']);
        $lawyerEmp = Employee::factory()->create();
        $lawyer = $this->userWithRole('lawyer', ['cases.view_own']);
        $lawyerEmp->update(['user_id' => $lawyer->id]);

        $this->defineField(['key' => 'contract_number', 'type' => 'text', 'view_roles' => null, 'display_in' => ['details']]);
        $this->defineField(['key' => 'secret_value', 'type' => 'currency', 'view_roles' => ['admin'], 'edit_roles' => ['admin'], 'display_in' => ['details'], 'sort_order' => 2]);
        $client = $this->client();

        $id = $this->actingAsToken($owner)->postJson('/api/cases', [
            'internal_number' => 'K-3', 'title' => 'قضية', 'client_id' => $client->id,
            'responsible_lawyer_id' => $lawyerEmp->id,
            'custom_fields' => ['contract_number' => 'C-1', 'secret_value' => 5000],
        ])->json('data.id');

        // المالك يرى الحقلين.
        $ownerKeys = collect($this->actingAsToken($owner)->getJson("/api/cases/{$id}")->json('data.custom_fields'))->pluck('key');
        $this->assertTrue($ownerKeys->contains('contract_number'));
        $this->assertTrue($ownerKeys->contains('secret_value'));

        // المحامي (قضيته) يرى العادي فقط، لا الحقل السرّي.
        $lawyerKeys = collect($this->actingAsToken($lawyer)->getJson("/api/cases/{$id}")->json('data.custom_fields'))->pluck('key');
        $this->assertTrue($lawyerKeys->contains('contract_number'));
        $this->assertFalse($lawyerKeys->contains('secret_value'));
    }

    public function test_lawyer_cannot_change_restricted_field_atomically(): void
    {
        $owner = $this->userWithRole('owner', ['custom_fields.manage', 'cases.create', 'cases.view_all']);
        $lawyerEmp = Employee::factory()->create();
        $lawyer = $this->userWithRole('lawyer', ['cases.view_own', 'cases.update']);
        $lawyerEmp->update(['user_id' => $lawyer->id]);

        $this->defineField(['key' => 'secret_value', 'type' => 'currency', 'view_roles' => ['admin'], 'edit_roles' => ['admin'], 'display_in' => ['details', 'edit']]);
        $client = $this->client();

        $id = $this->actingAsToken($owner)->postJson('/api/cases', [
            'internal_number' => 'K-4', 'title' => 'الأصلي', 'client_id' => $client->id,
            'responsible_lawyer_id' => $lawyerEmp->id,
            'custom_fields' => ['secret_value' => 5000],
        ])->json('data.id');

        // المحامي يغيّر عنوان القضية + الحقل السرّي ⇒ رفض ذرّي يسمّي الحقل، وعنوان القضية لا يتغيّر.
        $this->actingAsToken($lawyer)->putJson("/api/cases/{$id}", [
            'title' => 'محاولة تغيير', 'custom_fields' => ['secret_value' => 9999],
        ])->assertStatus(422)->assertJsonPath('errors.code', 'custom_field_value_forbidden')
            ->assertJsonPath('errors.fields', ['secret_value']);

        $this->assertDatabaseHas('cases', ['id' => $id, 'title' => 'الأصلي']); // لم يتغيّر
        $this->assertDatabaseHas('custom_field_values', ['entity_id' => $id, 'value_number' => 5000]); // لم يتغيّر
    }

    public function test_lawyer_can_change_editable_field(): void
    {
        $owner = $this->userWithRole('owner', ['custom_fields.manage', 'cases.create', 'cases.view_all']);
        $lawyerEmp = Employee::factory()->create();
        $lawyer = $this->userWithRole('lawyer', ['cases.view_own', 'cases.update']);
        $lawyerEmp->update(['user_id' => $lawyer->id]);

        $this->defineField(['key' => 'note', 'type' => 'text', 'edit_roles' => ['lawyer'], 'display_in' => ['edit', 'details']]);
        $client = $this->client();

        $id = $this->actingAsToken($owner)->postJson('/api/cases', [
            'internal_number' => 'K-5', 'title' => 'قضية', 'client_id' => $client->id,
            'responsible_lawyer_id' => $lawyerEmp->id,
        ])->json('data.id');

        $this->actingAsToken($lawyer)->putJson("/api/cases/{$id}", [
            'custom_fields' => ['note' => 'ملاحظة المحامي'],
        ])->assertOk();

        $this->assertDatabaseHas('custom_field_values', ['entity_id' => $id, 'value_text' => 'ملاحظة المحامي']);
    }

    public function test_required_field_enforced_only_when_custom_fields_sent(): void
    {
        $owner = $this->userWithRole('owner', ['custom_fields.manage', 'cases.create', 'cases.view_all']);
        $this->defineField(['key' => 'court_type', 'type' => 'text', 'required' => true, 'display_in' => ['create', 'edit']]);
        $client = $this->client();

        // مع مفتاح custom_fields لكن دون الحقل الإلزامي ⇒ 422.
        $this->actingAsToken($owner)->postJson('/api/cases', [
            'internal_number' => 'K-6', 'title' => 'قضية', 'client_id' => $client->id,
            'custom_fields' => [],
        ])->assertStatus(422);

        // بلا مفتاح custom_fields إطلاقاً (مسار قديم/استيراد) ⇒ لا يُفرَض الإلزامي.
        $this->actingAsToken($owner)->postJson('/api/cases', [
            'internal_number' => 'K-7', 'title' => 'قضية', 'client_id' => $client->id,
        ])->assertStatus(201);
    }

    public function test_invalid_dropdown_value_is_rejected(): void
    {
        $owner = $this->userWithRole('owner', ['custom_fields.manage', 'cases.create', 'cases.view_all']);
        $this->defineField(['key' => 'grade', 'type' => 'dropdown', 'options' => ['A', 'B']]);
        $client = $this->client();

        $this->actingAsToken($owner)->postJson('/api/cases', [
            'internal_number' => 'K-8', 'title' => 'قضية', 'client_id' => $client->id,
            'custom_fields' => ['grade' => 'Z'],
        ])->assertStatus(422);

        $this->assertDatabaseMissing('cases', ['internal_number' => 'K-8']); // ذرّي — لا قضية
    }

    public function test_deleting_definition_with_values_is_blocked(): void
    {
        $owner = $this->userWithRole('owner', ['custom_fields.manage', 'cases.create', 'cases.view_all']);
        $def = $this->defineField(['key' => 'contract_number', 'type' => 'text']);
        $client = $this->client();

        $this->actingAsToken($owner)->postJson('/api/cases', [
            'internal_number' => 'K-9', 'title' => 'قضية', 'client_id' => $client->id,
            'custom_fields' => ['contract_number' => 'C-1'],
        ])->assertStatus(201);

        $this->actingAsToken($owner)->deleteJson("/api/admin/custom-fields/{$def->id}")->assertStatus(422);
        $this->assertDatabaseHas('custom_field_definitions', ['id' => $def->id]); // لم يُحذَف
    }

    public function test_form_schema_create_returns_editable_fields_only(): void
    {
        $lawyer = $this->userWithRole('lawyer', ['cases.create', 'cases.view_own']);
        $this->defineField(['key' => 'note', 'type' => 'text', 'edit_roles' => null, 'display_in' => ['create', 'edit']]);
        $this->defineField(['key' => 'secret', 'type' => 'text', 'edit_roles' => ['admin'], 'display_in' => ['create'], 'sort_order' => 2]);

        $schema = $this->actingAsToken($lawyer)->getJson('/api/cases/custom-fields/form?context=create')->assertOk()->json('data');
        $keys = collect($schema)->pluck('key');

        $this->assertTrue($keys->contains('note'));      // قابل للتعديل ⇒ مدخل
        $this->assertFalse($keys->contains('secret'));   // غير قابل للتعديل ⇒ لا يظهر في الإنشاء
    }

    public function test_form_schema_edit_marks_editable_and_guards_case_access(): void
    {
        $owner = $this->userWithRole('owner', ['custom_fields.manage', 'cases.create', 'cases.view_all']);
        $lawyerEmp = Employee::factory()->create();
        $lawyer = $this->userWithRole('lawyer', ['cases.view_own', 'cases.update']);
        $lawyerEmp->update(['user_id' => $lawyer->id]);

        $this->defineField(['key' => 'note', 'type' => 'text', 'edit_roles' => null, 'display_in' => ['edit', 'details']]);
        $this->defineField(['key' => 'secret', 'type' => 'currency', 'view_roles' => null, 'edit_roles' => ['admin'], 'display_in' => ['edit', 'details'], 'sort_order' => 2]);
        $client = $this->client();

        $id = $this->actingAsToken($owner)->postJson('/api/cases', [
            'internal_number' => 'K-20', 'title' => 'قضية', 'client_id' => $client->id,
            'responsible_lawyer_id' => $lawyerEmp->id,
            'custom_fields' => ['secret' => 7000],
        ])->json('data.id');

        $schema = collect($this->actingAsToken($lawyer)->getJson("/api/cases/custom-fields/form?context=edit&case={$id}")->assertOk()->json('data'))->keyBy('key');
        $this->assertTrue($schema['note']['editable']);       // قابل للتعديل ⇒ مدخل
        $this->assertFalse($schema['secret']['editable']);    // مرئي لكن غير قابل للتعديل ⇒ للعرض فقط
        $this->assertSame(7000.0, (float) $schema['secret']['value']);

        // محامٍ غير مسنَد (عزل القضية) ⇒ 403 حتى على المخطّط.
        $otherEmp = Employee::factory()->create();
        $other = $this->userWithRole('lawyer2', ['cases.view_own', 'cases.update']);
        $otherEmp->update(['user_id' => $other->id]);
        $this->actingAsToken($other)->getJson("/api/cases/custom-fields/form?context=edit&case={$id}")->assertStatus(403);
    }

    public function test_index_batch_attaches_list_context_fields(): void
    {
        $owner = $this->userWithRole('owner', ['custom_fields.manage', 'cases.create', 'cases.view_all']);
        $this->defineField(['key' => 'contract_number', 'type' => 'text', 'display_in' => ['list', 'details']]);
        $client = $this->client();

        foreach (['K-10', 'K-11'] as $i => $no) {
            $this->actingAsToken($owner)->postJson('/api/cases', [
                'internal_number' => $no, 'title' => "قضية {$i}", 'client_id' => $client->id,
                'custom_fields' => ['contract_number' => "C-{$i}"],
            ])->assertStatus(201);
        }

        $items = $this->actingAsToken($owner)->getJson('/api/cases')->assertOk()->json('data');
        foreach ($items as $item) {
            $keys = collect($item['custom_fields'])->pluck('key');
            $this->assertTrue($keys->contains('contract_number'));
        }
    }
}
