<?php

namespace Modules\Leave\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HR\Models\Employee;
use Modules\Leave\Models\LeaveRequest;
use Modules\Leave\Services\LeaveService;

/**
 * دورة طلبات الإجازة (Issue #17): تقديم/عرض/اعتماد/رفض/إلغاء.
 * تقديم: leaves.request · عرض الكل: leaves.view_all · اعتماد/رفض/إلغاء: leaves.approve.
 */
class LeaveRequestController
{
    public function __construct(private readonly LeaveService $service) {}

    /** GET /api/leave-requests — قائمة مع تصفية (موظف/حالة/نوع) وترقيم. */
    public function index(Request $request): JsonResponse
    {
        $query = LeaveRequest::query()
            ->with(['employee:id,full_name_ar,employee_no', 'leaveType:id,name,code']);

        foreach (['employee_id', 'status', 'leave_type_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        $perPage = min((int) $request->query('per_page', 20), 100);
        $page = $query->orderByDesc('start_date')->paginate($perPage);

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'page' => $page->currentPage(), 'per_page' => $page->perPage(),
                'total' => $page->total(), 'total_pages' => $page->lastPage(),
            ],
            'errors' => null,
        ]);
    }

    /** POST /api/leave-requests — تقديم طلب. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'attachment_path' => ['nullable', 'string', 'max:255'],
        ]);
        $employee = Employee::findOrFail($data['employee_id']);

        return $this->ok($this->service->request($employee, $data, $request), 201);
    }

    /** POST /api/leave-requests/{leave_request}/approve */
    public function approve(Request $request, LeaveRequest $leave_request): JsonResponse
    {
        return $this->ok($this->service->approve($leave_request, $request));
    }

    /** POST /api/leave-requests/{leave_request}/reject */
    public function reject(Request $request, LeaveRequest $leave_request): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        return $this->ok($this->service->reject($leave_request, $data['reason'], $request));
    }

    /** POST /api/leave-requests/{leave_request}/cancel */
    public function cancel(Request $request, LeaveRequest $leave_request): JsonResponse
    {
        return $this->ok($this->service->cancel($leave_request, $request));
    }

    private function ok($data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => null, 'errors' => null], $status);
    }
}
