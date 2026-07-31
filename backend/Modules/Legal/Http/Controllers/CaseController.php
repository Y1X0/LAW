<?php

namespace Modules\Legal\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HR\Models\Employee;
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
    public function __construct(private readonly CaseService $service) {}

    /** GET /api/cases — قائمة مُنطَّقة (own/all) مع تصفية وبحث وترقيم. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = LegalCase::query()->with(['client:id,name', 'responsibleLawyer:id,full_name_ar']);

        if (! $user->hasPermission('cases.view_all')) {
            $employee = $user->employee;
            if ($employee === null) {
                return $this->forbidden('NO_LINKED_EMPLOYEE');
            }
            $query->whereHas('assignments', fn ($a) => $a->where('employee_id', $employee->id));
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('internal_number', 'like', "%{$search}%")
                    ->orWhere('court_case_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%");
            });
        }

        foreach (['client_id', 'status', 'case_type'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        $perPage = min((int) $request->query('per_page', 15), 100);
        $page = $query->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'total_pages' => $page->lastPage(),
            ],
            'errors' => null,
        ]);
    }

    /** GET /api/cases/{case} — 403 إن لم تكن القضية مسندة للمحامي (بلا view_all). */
    public function show(Request $request, LegalCase $case): JsonResponse
    {
        if ($denied = $this->guardView($request->user(), $case)) {
            return $denied;
        }

        return $this->ok($case->load(['client', 'responsibleLawyer:id,full_name_ar', 'assignments.employee:id,full_name_ar']));
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

    /** يتحقّق من صلاحية رؤية قضية بعينها؛ يعيد ردّ 403 موحّداً عند المنع، أو null عند السماح. */
    private function guardView(User $user, LegalCase $case): ?JsonResponse
    {
        if ($user->hasPermission('cases.view_all')) {
            return null;
        }
        $employee = $user->employee;
        if ($employee === null) {
            return $this->forbidden('NO_LINKED_EMPLOYEE');
        }

        return $case->isAssignedTo($employee->id) ? null : $this->forbidden('FORBIDDEN');
    }

    private function forbidden(string $code): JsonResponse
    {
        return response()->json(['data' => null, 'meta' => null, 'errors' => ['code' => $code]], 403);
    }

    private function ok($data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => null, 'errors' => null], $status);
    }
}
