<?php

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\HR\Models\Employee;

/**
 * إسناد مكوّن راتب لموظف بقيمة وفترة فعالية (Issue #33) — تاريخي.
 */
class EmployeeSalaryComponent extends Model
{
    protected $fillable = [
        'employee_id', 'salary_component_id', 'value',
        'effective_from', 'effective_to', 'is_active', 'created_by',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class, 'salary_component_id');
    }
}
