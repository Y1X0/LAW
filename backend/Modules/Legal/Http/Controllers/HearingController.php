<?php

namespace Modules\Legal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Legal\Concerns\AuthorizesCaseAccess;
use Modules\Legal\Http\Requests\PostponeHearingRequest;
use Modules\Legal\Http\Requests\StoreHearingRequest;
use Modules\Legal\Http\Requests\UpdateHearingRequest;
use Modules\Legal\Models\Hearing;
use Modules\Legal\Models\LegalCase;
use Modules\Legal\Services\HearingService;

/**
 * إدارة الجلسات (Legal / LC-3).
 *
 * الرؤية ترث عزل القضية (cases.view_own/view_all + حارس LC-2)؛ الكتابة hearings.manage.
 * استعلامات مدمجة: scope=upcoming|past|postponed.
 */
class HearingController
{
    use AuthorizesCaseAccess;

    public function __construct(private readonly HearingService $service) {}

    /** GET /api/hearings — قائمة مُنطَّقة عبر القضايا المرئية + فلتر scope. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Hearing::query()->with(['case:id,internal_number,title']);

        if (! $user->hasPermission('cases.view_all')) {
            $employee = $user->employee;
            if ($employee === null) {
                return $this->caseForbidden('NO_LINKED_EMPLOYEE');
            }
            $query->whereHas('case.assignments', fn ($a) => $a->where('employee_id', $employee->id));
        }

        $this->applyScope($query, $request->query('scope'));

        if ($request->filled('case_id')) {
            $query->where('case_id', $request->query('case_id'));
        }

        $perPage = min((int) $request->query('per_page', 15), 100);
        $page = $query->orderBy('scheduled_at')->paginate($perPage);

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

    /** GET /api/cases/{case}/hearings — جلسات قضية (بحارس رؤية القضية) + فلتر scope. */
    public function caseIndex(Request $request, LegalCase $case): JsonResponse
    {
        if ($denied = $this->guardCaseView($request->user(), $case)) {
            return $denied;
        }

        $query = $case->hearings()->getQuery();
        $this->applyScope($query, $request->query('scope'));

        return $this->ok($query->orderBy('scheduled_at')->get());
    }

    /** GET /api/hearings/{hearing} */
    public function show(Request $request, Hearing $hearing): JsonResponse
    {
        if ($denied = $this->guardCaseView($request->user(), $hearing->case)) {
            return $denied;
        }

        return $this->ok($hearing->load('case:id,internal_number,title'));
    }

    /** POST /api/cases/{case}/hearings */
    public function store(StoreHearingRequest $request, LegalCase $case): JsonResponse
    {
        $hearing = $this->service->create($case, $request->validated(), $request);

        return $this->ok($hearing, 201);
    }

    /** PUT /api/hearings/{hearing} */
    public function update(UpdateHearingRequest $request, Hearing $hearing): JsonResponse
    {
        $hearing = $this->service->update($hearing, $request->validated(), $request);

        return $this->ok($hearing);
    }

    /** POST /api/hearings/{hearing}/postpone — يحفظ القديمة وينشئ جديدة. */
    public function postpone(PostponeHearingRequest $request, Hearing $hearing): JsonResponse
    {
        $data = $request->validated();
        [$old, $new] = $this->service->postpone($hearing, $data['scheduled_at'], $data['postponed_reason'], $request);

        return $this->ok(['postponed' => $old, 'new_hearing' => $new], 201);
    }

    /** POST /api/hearings/{hearing}/cancel */
    public function cancel(Request $request, Hearing $hearing): JsonResponse
    {
        $hearing = $this->service->cancel($hearing, $request);

        return $this->ok($hearing);
    }

    /** يطبّق فلتر الحالة الزمنية على الاستعلام (upcoming/past/postponed). */
    private function applyScope($query, ?string $scope): void
    {
        match ($scope) {
            'upcoming' => $query->upcoming(),
            'past' => $query->past(),
            'postponed' => $query->postponed(),
            default => null,
        };
    }

    private function ok($data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => null, 'errors' => null], $status);
    }
}
