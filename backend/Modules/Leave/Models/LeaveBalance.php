<?php

namespace Modules\Leave\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\HR\Models\Employee;

/**
 * رصيد إجازة لموظف/نوع/سنة (Issue #17). المتبقي = المستحق − المستهلك.
 */
class LeaveBalance extends Model
{
    protected $fillable = [
        'employee_id', 'leave_type_id', 'year', 'entitled_days', 'consumed_days',
    ];

    protected $casts = [
        'year' => 'integer',
        'entitled_days' => 'float',
        'consumed_days' => 'float',
    ];

    protected $appends = ['remaining_days'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    /** الرصيد المتبقي المحسوب. */
    protected function remainingDays(): Attribute
    {
        return Attribute::get(fn () => round($this->entitled_days - $this->consumed_days, 1));
    }
}
