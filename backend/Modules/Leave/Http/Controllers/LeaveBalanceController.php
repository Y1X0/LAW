<?php

namespace Modules\Leave\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Concerns\RecordsAudit;
use Modules\HR\Models\Employee;
use Modules\Leave\Models\LeaveBalance;

/**
 * أرصدة الإجازات (Issue #17). عرض: leaves.view_all · منح/تعديل: leaves.approve.
 */
class LeaveBalanceController
{
    use RecordsAudit;

    /** GET /api/employees/{employee}/leave-balances — أرصدة موظف (اختياري ?year=). */
    public function index(Request $request, Employee $employee): JsonResponse
    {
        $query = LeaveBalance::where('employee_id', $employee->id)->with('leaveType:id,name,code');
        if ($request->filled('year')) {
            $query->where('year', (int) $request->query('year'));
        }

        return $this->ok($query->orderByDesc('year')->get());
    }

    /** POST /api/employees/{employee}/leave-balances — منح/تعديل استحقاق (upsert). */
    public function store(Request $request, Employee $employee): JsonResponse
    {
        $data = $request->validate([
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'entitled_days' => ['required', 'numeric', 'min:0', 'max:365'],
        ]);

        $balance = LeaveBalance::updateOrCreate(
            ['employee_id' => $employee->id, 'leave_type_id' => $data['leave_type_id'], 'year' => $data['year']],
            ['entitled_days' => $data['entitled_days']],
        );

        $this->recordAudit($request, 'leave_balance_set', LeaveBalance::class, $balance->id, [
            'employee_id' => $employee->id, 'year' => $data['year'], 'entitled_days' => $data['entitled_days'],
        ]);

        return $this->ok($balance, $balance->wasRecentlyCreated ? 201 : 200);
    }

    private function ok($data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => null, 'errors' => null], $status);
    }
}
