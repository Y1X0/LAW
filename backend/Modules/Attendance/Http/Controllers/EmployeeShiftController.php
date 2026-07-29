<?php

namespace Modules\Attendance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Attendance\Models\EmployeeShift;
use Modules\Core\Concerns\RecordsAudit;
use Modules\HR\Models\Employee;

/**
 * إسناد الموظفين لنوبات العمل (Issue #15). قراءة: attendance.view · كتابة: attendance.manual.
 */
class EmployeeShiftController
{
    use RecordsAudit;

    public function index(Employee $employee): JsonResponse
    {
        $shifts = EmployeeShift::where('employee_id', $employee->id)
            ->with('shift:id,name,start_time,end_time')
            ->orderByDesc('from_date')
            ->get();

        return response()->json(['data' => $shifts, 'meta' => null, 'errors' => null]);
    }

    public function store(Request $request, Employee $employee): JsonResponse
    {
        $data = $request->validate([
            'shift_id' => ['required', 'integer', 'exists:work_shifts,id'],
            'from_date' => ['required', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
        ]);
        $data['employee_id'] = $employee->id;

        $assignment = EmployeeShift::create($data);
        $this->recordAudit($request, 'employee_shift_assigned', Employee::class, $employee->id, [
            'shift_id' => $data['shift_id'], 'from_date' => $data['from_date'],
        ]);

        return response()->json(['data' => $assignment, 'meta' => null, 'errors' => null], 201);
    }
}
