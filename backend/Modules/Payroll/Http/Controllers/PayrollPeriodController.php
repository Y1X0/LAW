<?php

namespace Modules\Payroll\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Payroll\Models\PayrollPeriod;
use Modules\Payroll\Services\PayrollService;

/**
 * فترات الرواتب (Issue #32). قراءة: payroll.view · إنشاء: payroll.create.
 */
class PayrollPeriodController
{
    public function __construct(private readonly PayrollService $service) {}

    public function index(Request $request): JsonResponse
    {
        $query = PayrollPeriod::query()->with('branch:id,name')->withCount('runs');

        foreach (['year', 'month', 'status', 'branch_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        $perPage = min((int) $request->query('per_page', 20), 100);
        $page = $query->orderByDesc('year')->orderByDesc('month')->paginate($perPage);

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'page' => $page->currentPage(), 'per_page' => $page->perPage(),
                'total' => $page->total(), 'total_pages' => $page->lastPage(),
            ],
            'errors' => null,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'month' => ['required', 'integer', 'between:1,12'],
        ]);

        return $this->ok($this->service->createPeriod($data, $request), 201);
    }

    public function show(PayrollPeriod $payroll_period): JsonResponse
    {
        return $this->ok($payroll_period->load('branch:id,name')->loadCount('runs'));
    }

    private function ok($data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => null, 'errors' => null], $status);
    }
}
