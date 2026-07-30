<?php

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * مكوّن راتب في الكتالوج (Issue #33): بدل/خصم، بقيمة ثابتة أو نسبة.
 */
class SalaryComponent extends Model
{
    public const TYPES = ['allowance', 'deduction'];

    public const VALUE_TYPES = ['fixed', 'percentage'];

    protected $fillable = ['name', 'code', 'type', 'value_type', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
