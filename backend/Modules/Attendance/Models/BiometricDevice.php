<?php

namespace Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Models\Branch;

/**
 * جهاز بصمة مُسجَّل (Issue #16). يجرّد المورّد ونمط التكامل (push/pull)،
 * ويحمل توكناً سرياً مشفّراً يُتحقَّق منه في كل Push (docs/04 §2, §8).
 */
class BiometricDevice extends Model
{
    public const VENDORS = ['zkteco', 'hikvision', 'suprema', 'anviz'];

    public const API_MODES = ['push', 'pull'];

    public const STATUSES = ['online', 'offline', 'disabled'];

    protected $fillable = [
        'name', 'vendor', 'branch_id', 'api_mode', 'ip_address', 'port',
        'serial_number', 'auth_token', 'status', 'last_sync_at', 'last_seen_at', 'is_active',
    ];

    /** التوكن السري لا يُكشف أبداً في استجابات الـ API. */
    protected $hidden = ['auth_token'];

    protected $casts = [
        'auth_token' => 'encrypted',
        'port' => 'integer',
        'is_active' => 'boolean',
        'last_sync_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class, 'device_id');
    }
}
