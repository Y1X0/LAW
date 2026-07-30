<?php

namespace Modules\HR\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Department;
use Modules\HR\Factories\EmployeeFactory;

/**
 * الموظف (Issue #13). حالات: active / on_leave / suspended / terminated.
 */
class Employee extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['active', 'on_leave', 'suspended', 'terminated'];

    /** الحقول المالية/البنكية الحساسة (تُخفى إلا بصلاحية employees.salary.view). */
    public const SENSITIVE_FIELDS = ['basic_salary', 'bank_name', 'bank_account'];

    protected $fillable = [
        'user_id', 'branch_id', 'department_id', 'employee_no', 'full_name_ar', 'full_name_en',
        'national_id', 'birth_date', 'gender', 'phone', 'email', 'address', 'photo_path',
        'job_title', 'position_id', 'manager_id', 'hire_date', 'contract_type', 'contract_start', 'contract_end',
        'basic_salary', 'bank_name', 'bank_account', 'biometric_user_id', 'status', 'notes',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'hire_date' => 'date',
        'contract_start' => 'date',
        'contract_end' => 'date',
        'basic_salary' => 'decimal:2',
    ];

    /**
     * حساب المستخدم المرتبط بهذا الموظف (Issue #47). قد يكون null (موظف بلا حساب دخول).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** هل هذا السجلّ يخصّ المستخدم المُعطى؟ أساس عزل الخدمة الذاتية (لا وصول لموظف آخر). */
    public function isOwnedBy(User $user): bool
    {
        return $this->user_id !== null && $this->user_id === $user->id;
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(self::class, 'manager_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(EmployeeContract::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    protected static function newFactory(): Factory
    {
        return EmployeeFactory::new();
    }
}
