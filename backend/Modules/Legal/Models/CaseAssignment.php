<?php

namespace Modules\Legal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\HR\Models\Employee;

/**
 * إسناد محامٍ لقضية (Legal / LC-2). role: lead (رئيسي) / support (مساعد).
 */
class CaseAssignment extends Model
{
    public const ROLES = ['lead', 'support'];

    protected $fillable = ['case_id', 'employee_id', 'role'];

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
