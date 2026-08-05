<?php

namespace Modules\CustomFields\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Core\Seeders\RbacSeeder;
use Modules\CustomFields\Models\CustomFieldDefinition;

/**
 * تحقّق إنشاء تعريف حقل مخصّص. الصلاحية تُفرَض عبر middleware المسار (custom_fields.manage).
 * key فريد لكل كيان (نفس المفتاح مسموح في كيان آخر). options إلزامية لنوع القائمة المنسدلة.
 */
class StoreCustomFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $roles = array_keys(RbacSeeder::SYSTEM_ROLES);

        return [
            'entity' => ['required', Rule::in(CustomFieldDefinition::ENTITIES)],
            'key' => [
                'required', 'string', 'max:60', 'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('custom_field_definitions', 'key')->where('entity', $this->input('entity')),
            ],
            'label' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'type' => ['required', Rule::in(CustomFieldDefinition::TYPES)],
            'required' => ['boolean'],
            'options' => ['array', 'required_if:type,dropdown'],
            'options.*' => ['string', 'max:150'],
            'default_value' => ['nullable', 'string', 'max:1000'],
            'validation' => ['nullable', 'array'],
            'display_in' => ['nullable', 'array'],
            'display_in.*' => [Rule::in(CustomFieldDefinition::CONTEXTS)],
            'view_roles' => ['nullable', 'array'],
            'view_roles.*' => [Rule::in($roles)],
            'edit_roles' => ['nullable', 'array'],
            'edit_roles.*' => [Rule::in($roles)],
            'search_roles' => ['nullable', 'array'],
            'search_roles.*' => [Rule::in($roles)],
            'export_roles' => ['nullable', 'array'],
            'export_roles.*' => [Rule::in($roles)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ];
    }
}
