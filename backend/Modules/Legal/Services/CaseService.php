<?php

namespace Modules\Legal\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Core\Concerns\RecordsAudit;
use Modules\CustomFields\Services\CustomFieldValueService;
use Modules\Legal\Models\CaseAssignment;
use Modules\Legal\Models\LegalCase;

/**
 * منطق أعمال القضايا (Legal / LC-2): إنشاء/تعديل/إسناد/إغلاق — مع تدقيق.
 * الرؤية المُنطَّقة (view_own) تُطبَّق في المتحكّم عبر case_assignments.
 *
 * CASE-1: كل عملية تكتب أكثر من جدول (القضية + إسناد + خط زمني + تدقيق) ملفوفة في
 * DB::transaction — فشل جزئي (مثل الخط الزمني) لا يترك قضية دون إسناد lead الذي هو
 * أساس رؤية view_own.
 */
class CaseService
{
    use RecordsAudit;

    public function __construct(private readonly TimelineService $timeline) {}

    /**
     * ينشئ قضية. $origin يميّز المصدر لخطّ زمني صادق: الإدخال اليدوي يُسجَّل «تم إنشاء
     * القضية»، وهجرة قضية قديمة من نظام سابق تُسجَّل «تم استيراد القضية من النظام السابق»
     * (لأن «إنشاء» يضلّل لقضية عمرها سنوات). opened_date يبقى تاريخ الفتح القانوني.
     */
    public function create(array $data, Request $request, string $origin = 'manual'): LegalCase
    {
        $data['created_by'] = $request->user()?->id;
        // قيم الحقول المخصّصة تُعالَج عبر خدمتها لا كأعمدة قضية؛ تُستخرج قبل الإنشاء (Phase 12).
        $hasCustom = array_key_exists('custom_fields', $data);
        $custom = $data['custom_fields'] ?? [];
        unset($data['custom_fields']);

        return DB::transaction(function () use ($data, $request, $origin, $hasCustom, $custom) {
            $case = LegalCase::create($data);

            // المحامي الرئيسي يُسجَّل كإسناد lead تلقائياً (أساس رؤيته للقضية).
            if (! empty($case->responsible_lawyer_id)) {
                $this->syncAssignment($case, (int) $case->responsible_lawyer_id, 'lead');
            }

            $this->recordAudit($request, 'case_created', LegalCase::class, $case->id, ['internal_number' => $case->internal_number]);

            [$eventType, $title] = $origin === 'import'
                ? ['case_imported', 'تم استيراد القضية من النظام السابق']
                : ['case_created', 'تم إنشاء القضية'];
            $this->timeline->recordAuto($case, $eventType, $title, $request);

            // الاستيراد/المسارات القديمة لا تُرسل المفتاح ⇒ لا تُفرَض حقول إلزامية عليها.
            if ($hasCustom) {
                app(CustomFieldValueService::class)->write($custom, $case->customFieldEntityKey(), $case->id, LegalCase::class, $request, 'create');
            }

            return $case;
        });
    }

    public function update(LegalCase $case, array $data, Request $request): LegalCase
    {
        $hasCustom = array_key_exists('custom_fields', $data);
        $custom = $data['custom_fields'] ?? [];
        unset($data['custom_fields']);

        return DB::transaction(function () use ($case, $data, $request, $hasCustom, $custom) {
            $case->update($data);

            if (array_key_exists('responsible_lawyer_id', $data) && ! empty($data['responsible_lawyer_id'])) {
                $this->syncAssignment($case, (int) $data['responsible_lawyer_id'], 'lead');
            }

            $this->recordAudit($request, 'case_updated', LegalCase::class, $case->id);

            // تغيير قيم الحقول: يفرض edit_roles ويُدقّق كل تغيّر (Phase 12) — ذرّياً مع تحديث القضية.
            if ($hasCustom) {
                app(CustomFieldValueService::class)->write($custom, $case->customFieldEntityKey(), $case->id, LegalCase::class, $request, 'edit');
            }

            return $case;
        });
    }

    public function assign(LegalCase $case, int $employeeId, string $role, Request $request): CaseAssignment
    {
        return DB::transaction(function () use ($case, $employeeId, $role, $request) {
            $assignment = $this->syncAssignment($case, $employeeId, $role);

            if ($role === 'lead') {
                $case->update(['responsible_lawyer_id' => $employeeId]);
            }

            $this->recordAudit($request, 'case_lawyer_assigned', LegalCase::class, $case->id, [
                'employee_id' => $employeeId,
                'role' => $role,
            ]);
            $this->timeline->recordAuto($case, 'lawyer_assigned', 'تم إسناد محامٍ', $request, "employee_id={$employeeId}, role={$role}");

            return $assignment;
        });
    }

    public function unassign(LegalCase $case, int $employeeId, Request $request): void
    {
        DB::transaction(function () use ($case, $employeeId, $request) {
            $case->assignments()->where('employee_id', $employeeId)->delete();

            if ((int) $case->responsible_lawyer_id === $employeeId) {
                $case->update(['responsible_lawyer_id' => null]);
            }

            $this->recordAudit($request, 'case_lawyer_unassigned', LegalCase::class, $case->id, ['employee_id' => $employeeId]);
        });
    }

    public function close(LegalCase $case, Request $request): LegalCase
    {
        return DB::transaction(function () use ($case, $request) {
            $case->update(['status' => 'closed']);
            $this->recordAudit($request, 'case_closed', LegalCase::class, $case->id);
            $this->timeline->recordAuto($case, 'case_closed', 'تم إغلاق القضية', $request);

            return $case;
        });
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
