<?php

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\HR\Models\Employee;

/**
 * ملف راتب موظف (Issue #32) — تاريخي، النشط منه هو المرجع الحالي.
 */
class EmployeeSalaryProfile extends Model
{
    public const PAYMENT_METHODS = ['bank', 'cash', 'cheque'];

    protected $fillable = [
        'employee_id', 'basic_salary', 'currency', 'payment_method',
        'effective_from', 'effective_to', 'is_active', 'created_by',
    ];

    protected $casts = [
        'basic_salary' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
