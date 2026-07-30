<?php

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * مسير رواتب (تنفيذ) لفترة (Issue #32).
 */
class PayrollRun extends Model
{
    public const STATUSES = ['draft', 'processing', 'completed', 'approved', 'paid'];

    protected $fillable = [
        'payroll_period_id', 'status', 'notes', 'created_by', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }
}
