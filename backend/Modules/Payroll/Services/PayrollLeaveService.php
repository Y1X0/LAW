<?php

namespace Modules\Payroll\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Modules\Core\Concerns\RecordsAudit;
use Modules\HR\Models\Employee;
use Modules\Leave\Models\LeaveRequest;
use Modules\Payroll\Models\EmployeeSalaryProfile;
use Modules\Payroll\Models\PayrollLeaveSummary;
use Modules\Payroll\Models\PayrollRun;

/**
 * تكامل الإجازات مع الرواتب (Issue #35) — طبقة **قراءة فقط** فوق leave_requests.
 *
 * تحسب أيام الإجازة المدفوعة/بدون راتب من الإجازات **المعتمدة** ضمن شهر فترة الراتب،
 * وتحفظها كلقطة داخل Payroll. لا تعديل على وحدة الإجازات، ولا حساب خصم نهائي (#36).
 */
class PayrollLeaveService
{
    use RecordsAudit;

    /** عطلة نهاية الأسبوع (Carbon: 5=الجمعة، 6=السبت) — مطابقة لوحدة الإجازات. */
    private const WEEKEND = [Carbon::FRIDAY, Carbon::SATURDAY];

    /**
     * ملخّص إجازات موظف لشهر/سنة (قراءة فقط، بلا حفظ).
     *
     * @return array{paid_leave_days:float, unpaid_leave_days:float}
     */
    public function summarize(Employee $employee, int $year, int $month): array
    {
        $monthStart = Carbon::create($year, $month, 1)->startOfDay();
        $monthEnd = Carbon::create($year, $month, 1)->endOfMonth()->startOfDay();

        // الإجازات المعتمدة المتداخلة مع الشهر فقط.
        $requests = LeaveRequest::where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $monthEnd->toDateString())
            ->whereDate('end_date', '>=', $monthStart->toDateString())
            ->with('leaveType:id,is_paid')
            ->get();

        $paid = 0.0;
        $unpaid = 0.0;

        foreach ($requests as $req) {
            // أيام العمل ضمن تقاطع فترة الإجازة مع الشهر (استبعاد الويكند).
            $from = $req->start_date->greaterThan($monthStart) ? $req->start_date->copy() : $monthStart->copy();
            $to = $req->end_date->lessThan($monthEnd) ? $req->end_date->copy() : $monthEnd->copy();
            $days = $this->workingDays($from, $to);

            if ($req->leaveType?->is_paid) {
                $paid += $days;
            } else {
                $unpaid += $days;
            }
        }

        return ['paid_leave_days' => $paid, 'unpaid_leave_days' => $unpaid];
    }

    /** لقطة ملخّص إجازات موظف واحد ضمن مسير (idempotent لكل run+employee). */
    public function snapshotEmployee(PayrollRun $run, Employee $employee, Request $request): PayrollLeaveSummary
    {
        $period = $run->period;
        $summary = $this->summarize($employee, $period->year, $period->month);

        return PayrollLeaveSummary::updateOrCreate(
            ['payroll_run_id' => $run->id, 'employee_id' => $employee->id],
            $summary + ['created_by' => $request->user()?->id],
        );
    }

    /**
     * لقطة الإجازات لكل موظفي المسير (أصحاب ملف راتب نشط، ضمن فرع الفترة إن حُدّد).
     *
     * @return int عدد الموظفين الذين أُخِذت لهم لقطة
     */
    public function snapshotRun(PayrollRun $run, Request $request): int
    {
        // ثبات تاريخي: لا تُعاد اللقطة لمسير معتمد/مقفل.
        abort_unless(
            in_array($run->status, ['draft', 'processing'], true),
            422,
            'لا يمكن إعادة لقطة الإجازات لمسير معتمد أو مقفل.'
        );

        $employeeIds = EmployeeSalaryProfile::where('is_active', true)->pluck('employee_id')->unique();

        $query = Employee::whereIn('id', $employeeIds);
        if ($run->period->branch_id) {
            $query->where('branch_id', $run->period->branch_id);
        }
        $employees = $query->get();

        foreach ($employees as $employee) {
            $this->snapshotEmployee($run, $employee, $request);
        }

        $this->recordAudit($request, 'payroll_leave_snapshotted', PayrollRun::class, $run->id, [
            'payroll_period_id' => $run->payroll_period_id, 'employees' => $employees->count(),
        ]);

        return $employees->count();
    }

    /** عدد أيام العمل بين تاريخين شاملاً (استبعاد الويكند). */
    private function workingDays(Carbon $start, Carbon $end): int
    {
        $days = 0;
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            if (! in_array($date->dayOfWeek, self::WEEKEND, true)) {
                $days++;
            }
        }

        return $days;
    }
}
