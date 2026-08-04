<?php

namespace Modules\Dashboard\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Dashboard\Services\DashboardService;

/**
 * لوحة المؤشرات الإدارية الشاملة (Dashboard / Phase 7) — قراءة فقط، حارس واحد
 * (dashboard.view_management). المجاميع من DashboardService — لا حساب في الواجهة.
 */
class DashboardController
{
    public function __construct(private readonly DashboardService $service) {}

    /** GET /api/dashboard/summary */
    public function summary(): JsonResponse
    {
        return response()->json(['data' => $this->service->summary(), 'meta' => null, 'errors' => null]);
    }
}
