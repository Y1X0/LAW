<?php

namespace Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\HR\Models\Employee;

class AttendanceRecord extends Model
{
    public const STATUSES = ['present', 'absent', 'late', 'early_leave', 'leave', 'holiday', 'weekend'];

    protected $fillable = [
        'employee_id', 'branch_id', 'work_date', 'check_in', 'check_out',
        'worked_minutes', 'late_minutes', 'early_leave_minutes', 'overtime_minutes',
        'status', 'source', 'shift_id', 'approved_by', 'approved_at', 'notes', 'created_by',
    ];

    protected $casts = [
        'work_date' => 'date',
        'check_in' => 'datetime',
        'check_out' => 'datetime',
        'approved_at' => 'datetime',
        'worked_minutes' => 'integer',
        'late_minutes' => 'integer',
        'early_leave_minutes' => 'integer',
        'overtime_minutes' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class, 'shift_id');
    }

    public function isApproved(): bool
    {
        return $this->approved_by !== null;
    }
}
