<?php

namespace Modules\SelfService\Services;

use Modules\Attendance\Models\AttendanceRecord;
use Modules\HR\Models\Employee;
use Modules\Leave\Models\LeaveBalance;
use Modules\Payroll\Models\PayrollItem;

/**
 * لوحة الموظف الشخصية (Epic 9 / Issue #48).
 *
 * تجميع **قراءة فقط** لبيانات الموظف الحالي من الوحدات القائمة (HR/Leave/Attendance/Payroll)
 * — بلا أي منطق جديد ولا كتابة. كل استعلام مقيّد بـ `employee_id` للموظف المُعطى، فلا تسرّب
 * لبيانات موظف آخر. المسيّرات غير النهائية (مسودة) مستثناة من «آخر راتب».
 */
class MyDashboardService
{
    /** حالات المسير التي يظهر راتبها للموظف (نهائية فقط). */
    private const FINALIZED = ['approved', 'paid', 'locked'];

    public function forEmployee(Employee $employee): array
    {
        $employee->loadMissing(['branch:id,name', 'department:id,name', 'manager:id,full_name_ar']);

        return [
            'profile' => [
                'name' => $employee->full_name_ar,
                'employee_number' => $employee->employee_no,
                'job_title' => $employee->job_title,
                'branch' => $employee->branch?->name,
                'department' => $employee->department?->name,
                'manager' => $employee->manager?->full_name_ar,
            ],
            'leave_balance' => $this->leaveBalance($employee),
            'attendance_today' => $this->attendanceToday($employee),
            'last_payslip' => $this->lastPayslip($employee),
        ];
    }

    /** رصيد الإجازات للسنة الحالية (إجمالي متبقٍّ + تفصيل حسب النوع). */
    private function leaveBalance(Employee $employee): array
    {
        $balances = LeaveBalance::where('employee_id', $employee->id)
            ->where('year', now()->year)
            ->with('leaveType:id,name')
            ->get();

        return [
            'year' => now()->year,
            'total_remaining' => round((float) $balances->sum('remaining_days'), 1),
            'by_type' => $balances->map(fn (LeaveBalance $b) => [
                'type' => $b->leaveType?->name,
                'remaining' => (float) $b->remaining_days,
            ])->values()->all(),
        ];
    }

    /** سجلّ حضور اليوم (أو null إن لم يُسجَّل بعد). */
    private function attendanceToday(Employee $employee): ?array
    {
        $record = AttendanceRecord::where('employee_id', $employee->id)
            ->whereDate('work_date', today())
            ->first();

        if ($record === null) {
            return null;
        }

        return [
            'date' => $record->work_date->toDateString(),
            'status' => $record->status,
            'check_in' => $record->check_in?->toIso8601String(),
            'check_out' => $record->check_out?->toIso8601String(),
            'worked_minutes' => (int) $record->worked_minutes,
            'late_minutes' => (int) $record->late_minutes,
            'overtime_minutes' => (int) $record->overtime_minutes,
        ];
    }

    /** آخر راتب من مسير **نهائي** فقط (لا مسوّدات). */
    private function lastPayslip(Employee $employee): ?array
    {
        $item = PayrollItem::query()
            ->join('payroll_runs', 'payroll_runs.id', '=', 'payroll_items.payroll_run_id')
            ->join('payroll_periods', 'payroll_periods.id', '=', 'payroll_runs.payroll_period_id')
            ->where('payroll_items.employee_id', $employee->id)
            ->whereIn('payroll_runs.status', self::FINALIZED)
            ->orderByDesc('payroll_periods.year')
            ->orderByDesc('payroll_periods.month')
            ->first([
                'payroll_items.id',
                'payroll_items.net_amount',
                'payroll_items.currency',
                'payroll_periods.year',
                'payroll_periods.month',
            ]);

        if ($item === null) {
            return null;
        }

        return [
            'payroll_item_id' => $item->id,
            'year' => (int) $item->year,
            'month' => (int) $item->month,
            'net' => (float) $item->net_amount,
            'currency' => $item->currency,
        ];
    }
}
