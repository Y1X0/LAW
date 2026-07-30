<?php

namespace Modules\SelfService\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\SelfService\Services\MyAttendanceService;

/**
 * سجلّ حضور الموظف الذاتي (Issue #50). قراءة فقط بصلاحية attendance.view_own،
 * مقيّد بموظف المستخدم الحالي — لا تعديل حضور، ولا رؤية حضور موظف آخر.
 */
class MyAttendanceController
{
    public function __construct(private readonly MyAttendanceService $service) {}

    /** GET /api/me/attendance?from=&to= — سجلّ حضوري. */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $records = $this->service->listFor(
            $request->user()->employee,
            $filters['from'] ?? null,
            $filters['to'] ?? null,
        );

        return response()->json(['data' => $records, 'meta' => null, 'errors' => null]);
    }
}
