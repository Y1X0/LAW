<?php

namespace Modules\CustomFields\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Core\Seeders\RbacSeeder;
use Modules\CustomFields\Models\CustomFieldDefinition;

/**
 * تحقّق تحديث تعريف حقل مخصّص. entity وkey غير قابلين للتغيير (يُستبعدان) لتفادي يُتْم القيم
 * المرتبطة لاحقاً؛ التصحيح يكون بحقل جديد. الحقول الأخرى قابلة للتحديث جزئياً (sometimes).
 */
class UpdateCustomFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $roles = array_keys(RbacSeeder::SYSTEM_ROLES);

        return [
            'label' => ['sometimes', 'string', 'max:150'],
            'type' => ['sometimes', Rule::in(CustomFieldDefinition::TYPES)],
            'required' => ['boolean'],
            'options' => ['nullable', 'array'],
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
