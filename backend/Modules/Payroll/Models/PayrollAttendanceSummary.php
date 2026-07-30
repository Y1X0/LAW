<?php

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\HR\Models\Employee;

/**
 * لقطة ملخّص الحضور الشهري لموظف ضمن مسير (Issue #34).
 */
class PayrollAttendanceSummary extends Model
{
    protected $fillable = [
        'payroll_run_id', 'employee_id', 'total_work_days', 'absent_days',
        'worked_minutes', 'late_minutes', 'early_leave_minutes', 'overtime_minutes', 'created_by',
    ];

    protected $casts = [
        'total_work_days' => 'integer',
        'absent_days' => 'integer',
        'worked_minutes' => 'integer',
        'late_minutes' => 'integer',
        'early_leave_minutes' => 'integer',
        'overtime_minutes' => 'integer',
    ];

    protected $appends = ['overtime_hours'];

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** ساعات الإضافي المحسوبة من الدقائق. */
    protected function overtimeHours(): Attribute
    {
        return Attribute::get(fn () => round($this->overtime_minutes / 60, 2));
    }
}
