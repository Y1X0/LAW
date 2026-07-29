<?php

namespace Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\HR\Models\Employee;

/**
 * سجل بصمة خام في طبقة Staging (Issue #16, docs/04 §5).
 * الحالة: pending → matched/unmatched → processed. raw_payload يحفظ الحمولة كاملةً.
 */
class AttendanceLog extends Model
{
    public const STATUSES = ['pending', 'matched', 'unmatched', 'processed'];

    public const PUNCH_TYPES = ['in', 'out', 'unknown'];

    protected $fillable = [
        'device_id', 'branch_id', 'biometric_user_id', 'employee_id', 'punch_time',
        'punch_type', 'verify_mode', 'source', 'status', 'processed_at', 'raw_payload',
    ];

    protected $casts = [
        'punch_time' => 'datetime',
        'processed_at' => 'datetime',
        'raw_payload' => 'array',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(BiometricDevice::class, 'device_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
