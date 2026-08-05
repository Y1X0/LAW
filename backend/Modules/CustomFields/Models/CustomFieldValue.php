<?php

namespace Modules\CustomFields\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * قيمة حقل مخصّص (Phase 12 · PR-3) — صفٌّ يربط تعريفاً بصفّ كيان، بقيمة في العمود المطابق
 * لنوع الحقل. القراءة/الكتابة تمرّان عبر CustomFieldValueService (تحقّق + صلاحية + تدقيق).
 */
class CustomFieldValue extends Model
{
    protected $table = 'custom_field_values';

    protected $fillable = [
        'definition_id', 'entity', 'entity_id',
        'value_text', 'value_number', 'value_date', 'value_boolean',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'value_number' => 'decimal:4',
        'value_date' => 'date',
        'value_boolean' => 'boolean',
    ];

    public function definition(): BelongsTo
    {
        return $this->belongsTo(CustomFieldDefinition::class, 'definition_id');
    }
}
