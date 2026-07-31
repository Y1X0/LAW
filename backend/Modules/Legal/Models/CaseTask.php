<?php

namespace Modules\Legal\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\HR\Models\Employee;
use Modules\Legal\Factories\CaseTaskFactory;

/**
 * مهمة محامٍ (Legal / LC-5) — مسندة لموظف واحد، مرتبطة بقضية اختيارياً.
 * العزل بالإسناد: tasks.view_own (المُسنَد إليه) مقابل tasks.view_all (الإدارة).
 */
class CaseTask extends Model
{
    use HasFactory;

    protected $table = 'case_tasks';

    public const STATUSES = ['open', 'done'];

    public const PRIORITIES = ['low', 'normal', 'high'];

    protected $fillable = [
        'title', 'description', 'priority', 'due_date', 'status', 'completed_at',
        'assigned_to', 'case_id', 'created_by',
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
    ];

    protected $attributes = [
        'priority' => 'normal',
        'status' => 'open',
    ];

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_to');
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function newFactory(): Factory
    {
        return CaseTaskFactory::new();
    }
}
