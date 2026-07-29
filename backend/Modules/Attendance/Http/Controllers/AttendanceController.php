<?php

namespace Modules\Attendance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\Attendance\Services\AttendanceService;
use Modules\HR\Models\Employee;

/**
 * محرّك الحضور (Issue #15): عرض السجلات، دخول/خروج، تسجيل يدوي، واعتماد.
 */
class AttendanceController
{
    public function __construct(private readonly AttendanceService $service) {}

    /** GET /api/attendance — قائمة مع تصفية (موظف/تاريخ/حالة) وترقيم. */
    public function index(Request $request): JsonResponse
    {
        $query = AttendanceRecord::query()->with('employee:id,full_name_ar,employee_no');

        foreach (['employee_id', 'status'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }
        if ($request->filled('date')) {
            $query->whereDate('work_date', $request->query('date'));
        }
        if ($request->filled('from') && $request->filled('to')) {
            $query->whereBetween('work_date', [$request->query('from'), $request->query('to')]);
        }

        $perPage = min((int) $request->query('per_page', 20), 100);
        $page = $query->orderByDesc('work_date')->paginate($perPage);

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'page' => $page->currentPage(), 'per_page' => $page->perPage(),
                'total' => $page->total(), 'total_pages' => $page->lastPage(),
            ],
            'errors' => null,
        ]);
    }

    /** POST /api/attendance/check-in */
    public function checkIn(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'time' => ['nullable', 'date'],
        ]);
        $employee = Employee::findOrFail($data['employee_id']);
        $time = isset($data['time']) ? Carbon::parse($data['time']) : now();

        return $this->ok($this->service->checkIn($employee, $time, $request), 201);
    }

    /** POST /api/attendance/check-out */
    public function checkOut(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'time' => ['nullable', 'date'],
        ]);
        $employee = Employee::findOrFail($data['employee_id']);
        $time = isset($data['time']) ? Carbon::parse($data['time']) : now();

        return $this->ok($this->service->checkOut($employee, $time, $request));
    }

    /** POST /api/attendance/manual — تسجيل/تعديل يدوي (يتطلب اعتماداً). */
    public function storeManual(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'work_date' => ['required', 'date'],
            'check_in' => ['nullable', 'date'],
            'check_out' => ['nullable', 'date', 'after_or_equal:check_in'],
            'status' => ['required', Rule::in(AttendanceRecord::STATUSES)],
            'notes' => ['nullable', 'string'],
        ]);
        $employee = Employee::findOrFail($data['employee_id']);

        return $this->ok($this->service->storeManual($employee, $data, $request), 201);
    }

    /** POST /api/attendance/{record}/approve — اعتماد سجل. */
    public function approve(Request $request, AttendanceRecord $record): JsonResponse
    {
        return $this->ok($this->service->approve($record, $request));
    }

    private function ok($data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => null, 'errors' => null], $status);
    }
}
