<?php

namespace Modules\Notifications\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Notifications\Factories\NotificationFactory;

/**
 * إشعار داخل النظام لمستخدم (Notifications / Phase 8). يُنشأ حصراً عبر NotificationService.
 * الجدول `user_notifications` (لا يصطدم بجدول Laravel القياسي متعدّد الأشكال).
 */
class Notification extends Model
{
    use HasFactory;

    protected $table = 'user_notifications';

    protected $fillable = ['user_id', 'type', 'title', 'body', 'related_type', 'related_id', 'read_at'];

    protected $casts = ['read_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** غير المقروءة (read_at = null). */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    protected static function newFactory(): NotificationFactory
    {
        return NotificationFactory::new();
    }
}
