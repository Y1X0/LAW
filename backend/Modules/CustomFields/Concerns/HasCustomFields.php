<?php

namespace Modules\CustomFields\Concerns;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\CustomFields\Models\CustomFieldValue;

/**
 * يُدمج في أي نموذج كيان يدعم الحقول المخصّصة (Phase 12). يعرّف مفتاح الكيان (slug) وعلاقة
 * القيم. القراءة/الكتابة الفعلية تمرّان عبر CustomFieldValueService (صلاحيات + تدقيق) لا مباشرة.
 * يشترط ثابت CUSTOM_FIELD_ENTITY على النموذج المضيف.
 */
trait HasCustomFields
{
    public function customFieldEntityKey(): string
    {
        return static::CUSTOM_FIELD_ENTITY;
    }

    public function customFieldValues(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class, 'entity_id')
            ->where('entity', $this->customFieldEntityKey());
    }
}
