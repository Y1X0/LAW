<?php

namespace Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Models\Branch;

/**
 * جهاز بصمة مسجّل (Issue #16). المورّد ونمط التكامل يحدّدان المحوّل المستخدم.
 */
class BiometricDevice extends Model
{
    public const VENDORS = ['zkteco', 'hikvision', 'suprema', 'anviz'];

    public const API_MODES = ['push', 'pull'];

    public const STATUSES = ['online', 'offline', 'unknown'];

    protected $fillable = [
        'branch_id', 'name', 'vendor', 'api_mode', 'serial_number',
        'ip_address', 'port', 'secret', 'is_active', 'last_sync_at', 'status',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_sync_at' => 'datetime',
        'port' => 'integer',
    ];

    /** المفتاح السري لا يظهر في الاستجابات. */
    protected $hidden = ['secret'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class, 'device_id');
    }
}
