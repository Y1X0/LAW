<?php

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * مسير رواتب (تنفيذ) لفترة (Issue #32). دورة الحالة تكتمل في #37:
 * draft → (حساب #36 يُنتج payroll_items) → approved → locked.
 */
class PayrollRun extends Model
{
    public const STATUSES = ['draft', 'processing', 'completed', 'approved', 'paid', 'locked'];

    /** الحالات التي تسمح ببناء/إعادة الحساب (تُحمي الأرقام بعد الاعتماد). */
    public const MUTABLE_STATUSES = ['draft', 'processing'];

    protected $fillable = [
        'payroll_period_id', 'status', 'notes', 'created_by',
        'approved_by', 'approved_at', 'locked_by', 'locked_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'locked_at' => 'datetime',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }
}
