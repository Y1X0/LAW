<?php

namespace Modules\Legal\Services;

use Illuminate\Http\Request;
use Modules\Core\Concerns\RecordsAudit;
use Modules\Legal\Models\CaseAssignment;
use Modules\Legal\Models\LegalCase;

/**
 * منطق أعمال القضايا (Legal / LC-2): إنشاء/تعديل/إسناد/إغلاق — مع تدقيق.
 * الرؤية المُنطَّقة (view_own) تُطبَّق في المتحكّم عبر case_assignments.
 */
class CaseService
{
    use RecordsAudit;

    public function create(array $data, Request $request): LegalCase
    {
        $data['created_by'] = $request->user()?->id;
        $case = LegalCase::create($data);

        // المحامي الرئيسي يُسجَّل كإسناد lead تلقائياً (أساس رؤيته للقضية).
        if (! empty($case->responsible_lawyer_id)) {
            $this->syncAssignment($case, (int) $case->responsible_lawyer_id, 'lead');
        }

        $this->recordAudit($request, 'case_created', LegalCase::class, $case->id, ['internal_number' => $case->internal_number]);

        return $case;
    }

    public function update(LegalCase $case, array $data, Request $request): LegalCase
    {
        $case->update($data);

        if (array_key_exists('responsible_lawyer_id', $data) && ! empty($data['responsible_lawyer_id'])) {
            $this->syncAssignment($case, (int) $data['responsible_lawyer_id'], 'lead');
        }

        $this->recordAudit($request, 'case_updated', LegalCase::class, $case->id);

        return $case;
    }

    public function assign(LegalCase $case, int $employeeId, string $role, Request $request): CaseAssignment
    {
        $assignment = $this->syncAssignment($case, $employeeId, $role);

        if ($role === 'lead') {
            $case->update(['responsible_lawyer_id' => $employeeId]);
        }

        $this->recordAudit($request, 'case_lawyer_assigned', LegalCase::class, $case->id, [
            'employee_id' => $employeeId,
            'role' => $role,
        ]);

        return $assignment;
    }

    public function unassign(LegalCase $case, int $employeeId, Request $request): void
    {
        $case->assignments()->where('employee_id', $employeeId)->delete();

        if ((int) $case->responsible_lawyer_id === $employeeId) {
            $case->update(['responsible_lawyer_id' => null]);
        }

        $this->recordAudit($request, 'case_lawyer_unassigned', LegalCase::class, $case->id, ['employee_id' => $employeeId]);
    }

    public function close(LegalCase $case, Request $request): LegalCase
    {
        $case->update(['status' => 'closed']);
        $this->recordAudit($request, 'case_closed', LegalCase::class, $case->id);

        return $case;
    }

    /** إنشاء/تحديث إسناد محامٍ (idempotent على مستوى (case, employee)). */
    private function syncAssignment(LegalCase $case, int $employeeId, string $role): CaseAssignment
    {
        return CaseAssignment::updateOrCreate(
            ['case_id' => $case->id, 'employee_id' => $employeeId],
            ['role' => $role],
        );
    }
}
