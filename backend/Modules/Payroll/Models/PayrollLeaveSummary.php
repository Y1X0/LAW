<?php

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\HR\Models\Employee;

/**
 * لقطة ملخّص الإجازات الشهري لموظف ضمن مسير (Issue #35).
 */
class PayrollLeaveSummary extends Model
{
    protected $fillable = [
        'payroll_run_id', 'employee_id', 'paid_leave_days', 'unpaid_leave_days', 'created_by',
    ];

    protected $casts = [
        'paid_leave_days' => 'decimal:1',
        'unpaid_leave_days' => 'decimal:1',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
