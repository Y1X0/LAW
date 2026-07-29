<?php

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * سجل التدقيق (Append-Only): لا updated_at، ويُمنع التعديل والحذف على مستوى
 * التطبيق (booted). للتحصين الكامل في الإنتاج تُضاف قيود على مستوى قاعدة البيانات
 * (REVOKE UPDATE/DELETE أو Triggers) — راجع docs/05 §5.4.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'action', 'auditable_type', 'auditable_id',
        'old_values', 'new_values', 'ip_address', 'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    /**
     * فرض سياسة Append-Only: رفض أي تعديل أو حذف لسجل تدقيق.
     */
    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new \RuntimeException('سجلات التدقيق غير قابلة للتعديل (Append-Only).');
        });

        static::deleting(function (): void {
            throw new \RuntimeException('سجلات التدقيق غير قابلة للحذف (Append-Only).');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
