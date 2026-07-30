<?php

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Models\Branch;

/**
 * فترة رواتب شهرية (Issue #32).
 */
class PayrollPeriod extends Model
{
    public const STATUSES = ['draft', 'processing', 'approved', 'paid'];

    protected $fillable = ['branch_id', 'year', 'month', 'status', 'created_by'];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(PayrollRun::class);
    }
}
