<?php

namespace Modules\Leave\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * نوع إجازة وقواعده (Issue #17).
 */
class LeaveType extends Model
{
    public const CODES = ['annual', 'sick', 'emergency', 'unpaid'];

    protected $fillable = [
        'name', 'code', 'is_paid', 'consumes_balance', 'requires_attachment',
        'default_annual_days', 'max_consecutive_days', 'is_active',
    ];

    protected $casts = [
        'is_paid' => 'boolean',
        'consumes_balance' => 'boolean',
        'requires_attachment' => 'boolean',
        'default_annual_days' => 'float',
        'max_consecutive_days' => 'integer',
        'is_active' => 'boolean',
    ];
}
