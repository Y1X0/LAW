<?php

namespace Modules\Legal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Legal\Services\LawyerDashboardService;

/**
 * لوحة المحامي (Legal / LG-1) — تجميع ذاتي لأول شاشة في واجهة المحامي.
 * ذاتية (employee.linked + cases.view_own) — مُنطَّقة على قضايا المحامي.
 */
class LawyerDashboardController
{
    public function __construct(private readonly LawyerDashboardService $service) {}

    /** GET /api/me/legal-summary */
    public function summary(Request $request): JsonResponse
    {
        $data = $this->service->summary($request->user()->employee);

        return response()->json(['data' => $data, 'meta' => null, 'errors' => null]);
    }
}
