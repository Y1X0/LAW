<?php

namespace Modules\Legal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CustomFields\Services\CustomFieldValueService;
use Modules\HR\Models\Employee;
use Modules\Legal\Concerns\AuthorizesCaseAccess;
use Modules\Legal\Http\Requests\AssignLawyerRequest;
use Modules\Legal\Http\Requests\StoreCaseRequest;
use Modules\Legal\Http\Requests\UpdateCaseRequest;
use Modules\Legal\Models\LegalCase;
use Modules\Legal\Services\CaseService;

/**
 * إدارة القضايا (Legal / LC-2).
 *
 * الرؤية مُنطَّقة: من يملك cases.view_all يرى الكل؛ ومن يملك cases.view_own فقط
 * يرى القضايا المسندة إليه (عبر case_assignments) — ويجب أن يكون مرتبطاً بموظف.
 */
class CaseController
{
    use AuthorizesCaseAccess;

    public function __construct(private readonly CaseService $service) {}

    /** GET /api/cases — قائمة مُنطَّقة (own/all) مع تصفية وبحث وترقيم. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = LegalCase::query()->with(['client:id,name', 'responsibleLawyer:id,full_name_ar']);

        if (! $user->hasPermission('cases.view_all')) {
            $employee = $user->employee;
            if ($employee === null) {
                return $this->caseForbidden('NO_LINKED_EMPLOYEE');
            }
            $query->whereHas('assignments', fn ($a) => $a->where('employee_id', $employee->id));
        }

        if ($search = $request->query('search')) {
            // LOWER(...) LIKE: بحث غير حسّاس لحالة الأحرف عبر Postgres وsqlite معاً.
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(function ($q) use ($needle) {
                $q->whereRaw('LOWER(internal_number) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(court_case_number) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(title) LIKE ?', [$needle]);
            });
        }

        foreach (['client_id', 'status', 'case_type'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        $perPage = min((int) $request->query('per_page', 15), 100);
        $page = $query->orderByDesc('id')->paginate($perPage);

        // إلحاق الحقول المخصّصة المرئية (سياق الجدول) دفعةً واحدة — بلا N+1 (Phase 12).
        $items = $page->items();
        $custom = app(CustomFieldValueService::class)->read(
            LegalCase::CUSTOM_FIELD_ENTITY,
            array_map(fn (LegalCase $c) => $c->id, $items),
            $user,
            'list',
        );
        foreach ($items as $case) {
            $case->setAttribute('custom_fields', $custom[$case->id] ?? []);
        }

        return response()->json([
            'data' => $items,
            'meta' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'total_pages' => $page->lastPage(),
            ],
            'errors' => null,
        ]);
    }

    /**
     * GET /api/cases/custom-fields/form — مخطّط الحقول المخصّصة لنموذج الإنشاء/التعديل.
     * context=create ⇒ الحقول القابلة للتعديل. context=edit&case={id} ⇒ الحقول المرئية للقضية
     * (بعد guardCaseView) مع editable وقيمتها. الفرض النهائي في الحفظ (الخادم الحارس).
     */
    public function customFieldsForm(Request $request): JsonResponse
    {
        $context = $request->query('context') === 'edit' ? 'edit' : 'create';
        $caseId = null;

        if ($context === 'edit') {
            $case = LegalCase::findOrFail($request->query('case'));
            if ($denied = $this->guardCaseView($request->user(), $case)) {
                return $denied;
            }
            $caseId = $case->id;
        }

        return $this->ok(app(CustomFieldValueService::class)->formSchema(
            LegalCase::CUSTOM_FIELD_ENTITY, $caseId, $request->user(), $context,
        ));
    }

    /** GET /api/cases/{case} — 403 إن لم تكن القضية مسندة للمحامي (بلا view_all). */
    public function show(Request $request, LegalCase $case): JsonResponse
    {
        if ($denied = $this->guardCaseView($request->user(), $case)) {
            return $denied;
        }

        $case->load(['client', 'responsibleLawyer:id,full_name_ar', 'assignments.employee:id,full_name_ar']);
        // الحقول المخصّصة المرئية (سياق التفاصيل) — بعد اجتياز عزل القضية أعلاه (Phase 12).
        $custom = app(CustomFieldValueService::class)->read(LegalCase::CUSTOM_FIELD_ENTITY, [$case->id], $request->user(), 'details');
        $case->setAttribute('custom_fields', $custom[$case->id] ?? []);

        return $this->ok($case);
    }

    /** POST /api/cases */
    public function store(StoreCaseRequest $request): JsonResponse
    {
        $case = $this->service->create($request->validated(), $request);

        return $this->ok($case, 201);
    }

    /** PUT /api/cases/{case} */
    public function update(UpdateCaseRequest $request, LegalCase $case): JsonResponse
    {
        $case = $this->service->update($case, $request->validated(), $request);

        return $this->ok($case);
    }

    /** POST /api/cases/{case}/assign — إسناد محامٍ (lead/support). */
    public function assign(AssignLawyerRequest $request, LegalCase $case): JsonResponse
    {
        $data = $request->validated();
        $assignment = $this->service->assign($case, (int) $data['employee_id'], $data['role'], $request);

        return $this->ok($assignment, 201);
    }

    /** DELETE /api/cases/{case}/assign/{employee} — إلغاء إسناد محامٍ. */
    public function unassign(Request $request, LegalCase $case, Employee $employee): JsonResponse
    {
        $this->service->unassign($case, $employee->id, $request);

        return $this->ok(['message' => 'تم إلغاء الإسناد.']);
    }

    /** POST /api/cases/{case}/close — إغلاق القضية. */
    public function close(Request $request, LegalCase $case): JsonResponse
    {
        $case = $this->service->close($case, $request);

        return $this->ok($case);
    }

    private function ok($data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => null, 'errors' => null], $status);
    }
}
