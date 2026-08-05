<?php

namespace Modules\Legal\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\CustomFields\Concerns\HasCustomFields;
use Modules\HR\Models\Employee;
use Modules\Legal\Factories\LegalCaseFactory;

/**
 * قضية (Legal / LC-2). الاسم LegalCase لأن Case كلمة محجوزة في PHP؛ الجدول = cases.
 *
 * الرؤية مُنطَّقة عبر case_assignments: المحامي المسند فقط (view_own) مقابل الإدارة (view_all).
 */
class LegalCase extends Model
{
    use HasCustomFields, HasFactory;

    protected $table = 'cases';

    /** مفتاح الكيان للحقول المخصّصة (Phase 12). */
    public const CUSTOM_FIELD_ENTITY = 'case';

    public const STATUSES = ['open', 'pending', 'closed'];

    protected $fillable = [
        'internal_number', 'court_case_number', 'title', 'client_id', 'responsible_lawyer_id',
        'court_name', 'case_type', 'value', 'status', 'progress', 'opened_date', 'description', 'created_by',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'progress' => 'integer',
        'opened_date' => 'date',
    ];

    protected $attributes = [
        'status' => 'open',
        'progress' => 0,
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function responsibleLawyer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'responsible_lawyer_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(CaseAssignment::class, 'case_id');
    }

    public function hearings(): HasMany
    {
        return $this->hasMany(Hearing::class, 'case_id');
    }

    public function timelineEvents(): HasMany
    {
        return $this->hasMany(CaseTimelineEvent::class, 'case_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CaseDocument::class, 'case_id');
    }

    public function parties(): HasMany
    {
        return $this->hasMany(CaseParty::class, 'case_id');
    }

    public function archiveLocations(): HasMany
    {
        return $this->hasMany(CaseArchiveLocation::class, 'case_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** هل الموظف (المحامي) مُسنَد لهذه القضية؟ أساس عزل view_own. */
    public function isAssignedTo(int $employeeId): bool
    {
        return $this->assignments()->where('employee_id', $employeeId)->exists();
    }

    protected static function newFactory(): Factory
    {
        return LegalCaseFactory::new();
    }
}
