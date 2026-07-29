<?php

namespace Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\HR\Models\Employee;

/**
 * سجل بصمة خام في طبقة Staging (Issue #16). يُطابَق مع موظف ثم يُعالَج إلى attendance_records.
 */
class AttendanceLog extends Model
{
    public const STATUSES = ['pending', 'processed', 'unmatched'];

    public const PUNCH_TYPES = ['check_in', 'check_out', 'unknown'];

    protected $fillable = [
        'device_id', 'employee_id', 'biometric_user_id', 'punch_time',
        'punch_type', 'verify_mode', 'raw_payload', 'status', 'processed_at',
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
