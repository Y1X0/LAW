<?php

namespace Modules\SelfService\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\SelfService\Services\MyDashboardService;

/**
 * لوحة الموظف الشخصية (Issue #48). قراءة فقط بصلاحية dashboard.view_own،
 * مقيّدة بموظف المستخدم الحالي (الحارس employee.linked يضمن وجوده).
 */
class MyDashboardController
{
    public function __construct(private readonly MyDashboardService $service) {}

    /** GET /api/me/dashboard — ملخّص شخصي للموظف الحالي. */
    public function show(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        return response()->json([
            'data' => $this->service->forEmployee($employee),
            'meta' => null,
            'errors' => null,
        ]);
    }
}
