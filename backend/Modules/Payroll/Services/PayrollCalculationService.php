<?php

namespace Modules\Payroll\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Core\Concerns\RecordsAudit;
use Modules\HR\Models\Employee;
use Modules\Payroll\Models\EmployeeSalaryComponent;
use Modules\Payroll\Models\EmployeeSalaryProfile;
use Modules\Payroll\Models\PayrollAttendanceSummary;
use Modules\Payroll\Models\PayrollItem;
use Modules\Payroll\Models\PayrollLeaveSummary;
use Modules\Payroll\Models\PayrollRun;

/**
 * محرّك حساب الرواتب (Epic 8 / Issue #36).
 *
 * يجمع: الراتب الأساسي + المكوّنات (بدلات/خصومات) + مشتقّات الحضور (غياب/تأخير/إضافي)
 * + خصم الإجازات بدون راتب → Gross/Deductions/Net، ويحفظ لقطة نهائية قابلة للتدقيق.
 *
 * **قراءة فقط** من لقطات الحضور/الإجازات المجمّدة (#34/#35) — لا يقرأ الحضور/الإجازات
 * حيّاً ولا يعدّل أي مصدر. غير قابل لإعادة التشغيل بعد اعتماد المسير (immutable).
 *
 * قواعد المعدّلات الموثّقة (قابلة للضبط لاحقاً):
 *  - اليومي = الأساسي ÷ 30 · الساعة = اليومي ÷ 8 · الدقيقة = الساعة ÷ 60
 *  - الإضافي = الساعة × 1.5 × ساعات الإضافي
 */
class PayrollCalculationService
{
    use RecordsAudit;

    private const STANDARD_DAYS = 30;

    private const HOURS_PER_DAY = 8;

    private const OVERTIME_MULTIPLIER = 1.5;

    /** حساب المسير لكل موظفيه (أصحاب ملف راتب نشط، ضمن فرع الفترة). */
    public function calculateRun(PayrollRun $run, Request $request): int
    {
        // لقطة نهائية غير قابلة للتغيير بعد الاعتماد.
        abort_unless(
            in_array($run->status, ['draft', 'processing'], true),
            422,
            'لا يمكن إعادة حساب مسير معتمد أو مقفل.'
        );

        $employeeIds = EmployeeSalaryProfile::where('is_active', true)->pluck('employee_id')->unique();
        $query = Employee::whereIn('id', $employeeIds);
        if ($run->period->branch_id) {
            $query->where('branch_id', $run->period->branch_id);
        }
        $employees = $query->get();

        // F6 (تكامل مالي): لفّ حساب كل الموظفين في معاملة واحدة — فشل موظف في منتصف
        // المسير يتراجع بالكامل بدل ترك مسير محسوب جزئياً يبدو قابلاً للاعتماد.
        DB::transaction(function () use ($employees, $run, $request) {
            foreach ($employees as $employee) {
                $this->calculateEmployee($run, $employee, $request);
            }

            $this->recordAudit($request, 'payroll_calculated', PayrollRun::class, $run->id, [
                'payroll_period_id' => $run->payroll_period_id, 'employees' => $employees->count(),
            ]);
        });

        return $employees->count();
    }

    /** حساب راتب موظف واحد ضمن مسير (idempotent لكل run+employee). */
    public function calculateEmployee(PayrollRun $run, Employee $employee, Request $request): ?PayrollItem
    {
        $profile = EmployeeSalaryProfile::where('employee_id', $employee->id)->where('is_active', true)->first();
        if ($profile === null) {
            return null;
        }

        $basic = (float) $profile->basic_salary;
        $daily = self::STANDARD_DAYS > 0 ? $basic / self::STANDARD_DAYS : 0.0;
        $hourly = self::HOURS_PER_DAY > 0 ? $daily / self::HOURS_PER_DAY : 0.0;
        $minute = $hourly / 60;

        $earnings = [['code' => 'basic', 'amount' => $this->round($basic)]];
        $deductions = [];

        // مكوّنات الموظف النشطة (fixed = مبلغ ثابت، percentage = نسبة من الأساسي).
        $components = EmployeeSalaryComponent::where('employee_id', $employee->id)
            ->where('is_active', true)
            ->with('component:id,code,type,value_type')
            ->get();

        foreach ($components as $ec) {
            $comp = $ec->component;
            if ($comp === null) {
                continue;
            }
            $amount = $comp->value_type === 'percentage' ? $basic * ((float) $ec->value) / 100 : (float) $ec->value;
            $amount = $this->round($amount);
            $line = ['code' => $comp->type.':'.$comp->code, 'amount' => $amount];

            $comp->type === 'allowance' ? $earnings[] = $line : $deductions[] = $line;
        }

        // مشتقّات الحضور (من اللقطة المجمّدة #34).
        $att = PayrollAttendanceSummary::where('payroll_run_id', $run->id)->where('employee_id', $employee->id)->first();
        $absentDays = $att?->absent_days ?? 0;
        $lateMinutes = $att?->late_minutes ?? 0;
        $overtimeMinutes = $att?->overtime_minutes ?? 0;

        if ($overtimeMinutes > 0) {
            $earnings[] = ['code' => 'overtime', 'amount' => $this->round($hourly * self::OVERTIME_MULTIPLIER * ($overtimeMinutes / 60)), 'minutes' => $overtimeMinutes];
        }
        if ($absentDays > 0) {
            $deductions[] = ['code' => 'absence', 'amount' => $this->round($daily * $absentDays), 'days' => $absentDays];
        }
        if ($lateMinutes > 0) {
            $deductions[] = ['code' => 'late', 'amount' => $this->round($minute * $lateMinutes), 'minutes' => $lateMinutes];
        }

        // خصم الإجازات بدون راتب (من اللقطة المجمّدة #35). المدفوعة لا تُخصم.
        $leave = PayrollLeaveSummary::where('payroll_run_id', $run->id)->where('employee_id', $employee->id)->first();
        $unpaidDays = (float) ($leave?->unpaid_leave_days ?? 0);
        if ($unpaidDays > 0) {
            $deductions[] = ['code' => 'unpaid_leave', 'amount' => $this->round($daily * $unpaidDays), 'days' => $unpaidDays];
        }

        $allowancesTotal = $this->round(array_sum(array_column($earnings, 'amount')) - $this->round($basic));
        $deductionsTotal = $this->round(array_sum(array_column($deductions, 'amount')));
        $gross = $this->round($basic + $allowancesTotal);
        $net = $this->round($gross - $deductionsTotal);

        return PayrollItem::updateOrCreate(
            ['payroll_run_id' => $run->id, 'employee_id' => $employee->id],
            [
                // لقطة الموقع التنظيمي لحظة الحساب (#38) — تقارير تاريخية لا تتأثر بانتقال الموظف لاحقاً.
                'branch_id' => $employee->branch_id,
                'department_id' => $employee->department_id,
                'currency' => $profile->currency,
                'basic_salary' => $this->round($basic),
                'allowances_total' => $allowancesTotal,
                'deductions_total' => $deductionsTotal,
                'gross_amount' => $gross,
                'net_amount' => $net,
                'breakdown' => [
                    'rates' => ['daily' => $this->round($daily), 'hourly' => $this->round($hourly), 'minute' => round($minute, 4)],
                    'earnings' => $earnings,
                    'deductions' => $deductions,
                ],
                'created_by' => $request->user()?->id,
            ],
        );
    }

    private function round(float $value): float
    {
        return round($value, 2);
    }
}
