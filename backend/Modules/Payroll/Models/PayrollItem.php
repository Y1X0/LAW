<?php

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Department;
use Modules\HR\Models\Employee;

/**
 * نتيجة حساب راتب موظف ضمن مسير (Issue #36) — لقطة نهائية مع تفصيل البنود.
 * يجمّد أيضاً الموقع التنظيمي (branch_id/department_id) لحظة الحساب (#38) لتقارير تاريخية.
 */
class PayrollItem extends Model
{
    protected $fillable = [
        'payroll_run_id', 'employee_id', 'branch_id', 'department_id', 'currency', 'basic_salary',
        'allowances_total', 'deductions_total', 'gross_amount', 'net_amount', 'breakdown', 'created_by',
    ];

    protected $casts = [
        'basic_salary' => 'decimal:2',
        'allowances_total' => 'decimal:2',
        'deductions_total' => 'decimal:2',
        'gross_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'breakdown' => 'array',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** الفرع المجمّد لحظة الحساب (#38) — لتسمية تقارير التكلفة، لا يُعاد قراءته حيّاً. */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** القسم المجمّد لحظة الحساب (#38). */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
