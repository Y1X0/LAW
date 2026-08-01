<?php

namespace Modules\Payroll\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\Core\Concerns\RecordsAudit;
use Modules\HR\Models\Employee;
use Modules\Payroll\Models\EmployeeSalaryProfile;
use Modules\Payroll\Models\PayrollAttendanceSummary;
use Modules\Payroll\Models\PayrollRun;

/**
 * تكامل الحضور مع الرواتب (Issue #34) — طبقة **قراءة فقط** فوق attendance_records.
 *
 * تستخرج ملخّص الحضور الشهري لكل موظف وتحفظه كلقطة (Snapshot) داخل Payroll، فتثبت
 * الأرقام التاريخية. لا تعديل على وحدة الحضور، ولا حساب خصومات نهائية (ذلك في #36).
 */
class PayrollAttendanceService
{
    use RecordsAudit;

    /** الحالات التي تُحتسب كأيام عمل حضورية. */
    private const WORKED_STATUSES = ['present', 'late', 'early_leave'];

    /**
     * ملخّص حضور موظف لشهر/سنة (قراءة فقط، بلا حفظ).
     *
     * @return array{total_work_days:int, absent_days:int, worked_minutes:int, late_minutes:int, early_leave_minutes:int, overtime_minutes:int}
     */
    public function summarize(Employee $employee, int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1)->toDateString();
        $end = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

        $records = AttendanceRecord::where('employee_id', $employee->id)
            ->whereDate('work_date', '>=', $start)
            ->whereDate('work_date', '<=', $end)
            ->get(['status', 'worked_minutes', 'late_minutes', 'early_leave_minutes', 'overtime_minutes']);

        return [
            'total_work_days' => $records->whereIn('status', self::WORKED_STATUSES)->count(),
            'absent_days' => $records->where('status', 'absent')->count(),
            'worked_minutes' => (int) $records->sum('worked_minutes'),
            'late_minutes' => (int) $records->sum('late_minutes'),
            'early_leave_minutes' => (int) $records->sum('early_leave_minutes'),
            'overtime_minutes' => (int) $records->sum('overtime_minutes'),
        ];
    }

    /** لقطة ملخّص حضور موظف واحد ضمن مسير (idempotent لكل run+employee). */
    public function snapshotEmployee(PayrollRun $run, Employee $employee, Request $request): PayrollAttendanceSummary
    {
        $period = $run->period;
        $summary = $this->summarize($employee, $period->year, $period->month);

        return PayrollAttendanceSummary::updateOrCreate(
            ['payroll_run_id' => $run->id, 'employee_id' => $employee->id],
            $summary + ['created_by' => $request->user()?->id],
        );
    }

    /**
     * لقطة الحضور لكل موظفي المسير (أصحاب ملف راتب نشط، ضمن فرع الفترة إن حُدّد).
     *
     * @return int عدد الموظفين الذين أُخِذت لهم لقطة
     */
    public function snapshotRun(PayrollRun $run, Request $request): int
    {
        // ثبات تاريخي: لا تُعاد اللقطة لمسير معتمد/مقفل — تُبنى وهو مسودة/قيد المعالجة فقط.
        abort_unless(
            in_array($run->status, ['draft', 'processing'], true),
            422,
            'لا يمكن إعادة لقطة الحضور لمسير معتمد أو مقفل.'
        );

        $employeeIds = EmployeeSalaryProfile::where('is_active', true)->pluck('employee_id')->unique();

        $query = Employee::whereIn('id', $employeeIds);
        if ($run->period->branch_id) {
            $query->where('branch_id', $run->period->branch_id);
        }
        $employees = $query->get();

        // F6 (تكامل مالي): لقطة كل الموظفين في معاملة واحدة — فشل في المنتصف يتراجع بالكامل.
        DB::transaction(function () use ($employees, $run, $request) {
            foreach ($employees as $employee) {
                $this->snapshotEmployee($run, $employee, $request);
            }

            $this->recordAudit($request, 'payroll_attendance_snapshotted', PayrollRun::class, $run->id, [
                'payroll_period_id' => $run->payroll_period_id, 'employees' => $employees->count(),
            ]);
        });

        return $employees->count();
    }
}
