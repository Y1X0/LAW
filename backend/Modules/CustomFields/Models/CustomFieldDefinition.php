<?php

namespace Modules\CustomFields\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * تعريف حقل مخصّص (Custom Fields / Phase 12). صفٌّ واحد = حقل يضيفه المدير لكيان معيّن.
 * الأنواع والكيانات والسياقات ثوابت في الكلاس (extensible). صلاحيات الحقل قوائم أدوار.
 */
class CustomFieldDefinition extends Model
{
    protected $table = 'custom_field_definitions';

    /** الكيانات المضيفة المسموح بها (whitelist — يقابلها لاحقاً سِجلّ slug→model عند الربط). */
    public const ENTITIES = ['case', 'client', 'employee'];

    /** أنواع الحقول المدعومة في v1 (extensible: يُضاف نوع دون كسر). */
    public const TYPES = [
        'text', 'longtext', 'number', 'currency', 'date', 'boolean', 'email', 'phone', 'url', 'dropdown',
    ];

    /** سياقات العرض — أين يظهر الحقل. */
    public const CONTEXTS = ['create', 'edit', 'details', 'list'];

    protected $fillable = [
        'entity', 'key', 'label', 'description', 'type', 'required', 'options', 'default_value', 'validation',
        'display_in', 'view_roles', 'edit_roles', 'search_roles', 'export_roles',
        'sort_order', 'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'required' => 'boolean',
        'is_active' => 'boolean',
        'options' => 'array',
        'validation' => 'array',
        'display_in' => 'array',
        'view_roles' => 'array',
        'edit_roles' => 'array',
        'search_roles' => 'array',
        'export_roles' => 'array',
        'sort_order' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
