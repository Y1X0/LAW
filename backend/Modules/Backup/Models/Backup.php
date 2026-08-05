<?php

namespace Modules\Backup\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * نسخة احتياطية لقاعدة البيانات (Phase 13). البيانات الوصفية فقط؛ الملف على تخزين النسخ.
 */
class Backup extends Model
{
    /** أنواع النسخ — تقود الاحتفاظ المتدرّج (GFS). */
    public const KINDS = ['manual', 'daily', 'weekly', 'monthly'];

    protected $fillable = [
        'filename', 'disk', 'path', 'size_bytes', 'kind', 'status', 'trigger', 'error', 'created_by',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
