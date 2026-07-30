<?php

namespace Modules\SelfService\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\SelfService\Services\MyLeaveService;

/**
 * إجازات الموظف الذاتية (Issue #51). قراءة بصلاحية leave.view_own،
 * وتقديم طلب بصلاحية leave.request_own — دائماً باسم الموظف الحالي فقط.
 */
class MyLeaveController
{
    public function __construct(private readonly MyLeaveService $service) {}

    /** GET /api/me/leave/balance — رصيد إجازاتي. */
    public function balance(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->balance($request->user()->employee),
            'meta' => null, 'errors' => null,
        ]);
    }

    /** GET /api/me/leave/requests — طلباتي. */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->requests($request->user()->employee),
            'meta' => null, 'errors' => null,
        ]);
    }

    /** POST /api/me/leave/requests — تقديم طلب لنفسي فقط. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'leave_type_id' => ['required', 'integer'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'attachment_path' => ['nullable', 'string', 'max:255'],
        ]);

        // الموظف الحالي حصراً — أي employee_id في المُدخل يُتجاهَل.
        $leave = $this->service->submit($request->user()->employee, $data, $request);

        return response()->json([
            'data' => ['id' => $leave->id, 'status' => $leave->status, 'days' => (float) $leave->days],
            'meta' => null, 'errors' => null,
        ], 201);
    }
}
