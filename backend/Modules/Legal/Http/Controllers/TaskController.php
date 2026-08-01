<?php

namespace Modules\Legal\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Legal\Http\Requests\StoreTaskRequest;
use Modules\Legal\Http\Requests\UpdateTaskRequest;
use Modules\Legal\Models\CaseTask;
use Modules\Legal\Services\TaskService;

/**
 * مهام المحامي (Legal / LC-5).
 *
 * العزل بالإسناد: من يملك tasks.view_all يرى الكل؛ ومن يملك tasks.view_own فقط
 * يرى المهام المسندة إليه (ويجب أن يكون مرتبطاً بموظف). الإكمال للمُسنَد إليه أو الإدارة.
 */
class TaskController
{
    public function __construct(private readonly TaskService $service) {}

    /** GET /api/tasks — قائمة مُنطَّقة (own/all) + فلاتر وترقيم. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = CaseTask::query()->with(['assignee:id,full_name_ar', 'case:id,internal_number,title']);

        if (! $user->hasPermission('tasks.view_all')) {
            $employee = $user->employee;
            if ($employee === null) {
                return $this->forbidden('NO_LINKED_EMPLOYEE');
            }
            $query->where('assigned_to', $employee->id);
        }

        foreach (['status', 'case_id', 'priority'] as $filter) {
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

    /** GET /api/tasks/{task} — 403 إن لم تكن مسندة للمستخدم (بلا view_all). */
    public function show(Request $request, CaseTask $task): JsonResponse
    {
        if ($denied = $this->guardTaskAccess($request->user(), $task)) {
            return $denied;
        }

        return $this->ok($task->load(['assignee:id,full_name_ar', 'case:id,internal_number,title']));
    }

    /** POST /api/tasks */
    public function store(StoreTaskRequest $request): JsonResponse
    {
        $task = $this->service->create($request->validated(), $request);

        return $this->ok($task, 201);
    }

    /** PUT /api/tasks/{task} — تعديل (المُسنَد إليه أو الإدارة) — نفس عزل show/complete. */
    public function update(UpdateTaskRequest $request, CaseTask $task): JsonResponse
    {
        // SEC-2: أغلق IDOR — من لا يملك tasks.view_all لا يعدّل إلا مهمة مُسنَدة إليه،
        // تماماً كـ show()/complete(). بلا هذا الحارس كان حامل tasks.create يعدّل أي مهمة بالمُعرّف.
        if ($denied = $this->guardTaskAccess($request->user(), $task)) {
            return $denied;
        }

        $task = $this->service->update($task, $request->validated(), $request);

        return $this->ok($task);
    }

    /** PATCH /api/tasks/{task}/assign — إعادة إسناد (tasks.assign). */
    public function assign(Request $request, CaseTask $task): JsonResponse
    {
        $data = $request->validate(['employee_id' => ['required', 'integer', 'exists:employees,id']]);
        $task = $this->service->assign($task, (int) $data['employee_id'], $request);

        return $this->ok($task);
    }

    /** PATCH /api/tasks/{task}/complete — إكمال (المُسنَد إليه أو الإدارة) + completed_at. */
    public function complete(Request $request, CaseTask $task): JsonResponse
    {
        if ($denied = $this->guardTaskAccess($request->user(), $task)) {
            return $denied;
        }
        $task = $this->service->complete($task, $request);

        return $this->ok($task);
    }

    /** يسمح بالوصول لمهمة إن كان المستخدم مُسنَداً إليها أو يملك tasks.view_all. */
    private function guardTaskAccess(User $user, CaseTask $task): ?JsonResponse
    {
        if ($user->hasPermission('tasks.view_all')) {
            return null;
        }
        $employee = $user->employee;
        if ($employee === null) {
            return $this->forbidden('NO_LINKED_EMPLOYEE');
        }

        return (int) $task->assigned_to === $employee->id ? null : $this->forbidden('FORBIDDEN');
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
