<?php

namespace Modules\Legal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Legal\Http\Requests\StoreWorklogRequest;
use Modules\Legal\Models\DailyWorklog;
use Modules\Legal\Services\WorklogService;

/**
 * الإنجاز اليومي (Legal / LC-5).
 *
 * `me/worklog`: ذاتي بحت (employee.linked) — المحامي يرى/يكتب سجله لليوم فقط.
 * `worklog`: اطّلاع الإدارة (worklog.view_all).
 */
class WorklogController
{
    public function __construct(private readonly WorklogService $service) {}

    /** GET /api/me/worklog — سجلّي (الأحدث أولاً). */
    public function mine(Request $request): JsonResponse
    {
        $logs = DailyWorklog::where('employee_id', $request->user()->employee->id)
            ->orderByDesc('work_date')
            ->get();

        return $this->ok($logs);
    }

    /** POST /api/me/worklog — تسجيل/تحديث إنجاز اليوم (سجل واحد لليوم). */
    public function submit(StoreWorklogRequest $request): JsonResponse
    {
        $log = $this->service->submitToday($request->user()->employee, $request->validated(), $request);

        return $this->ok($log, 201);
    }

    /** GET /api/worklog — اطّلاع الإدارة على كل السجلات (فلتر employee_id اختياري). */
    public function index(Request $request): JsonResponse
    {
        $query = DailyWorklog::query()->with('employee:id,full_name_ar');

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->query('employee_id'));
        }

        $perPage = min((int) $request->query('per_page', 15), 100);
        $page = $query->orderByDesc('work_date')->paginate($perPage);

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

    private function ok($data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => null, 'errors' => null], $status);
    }
}
