<?php

namespace Modules\Payroll\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Concerns\RecordsAudit;
use Modules\HR\Models\Employee;
use Modules\Payroll\Models\EmployeeSalaryProfile;
use Modules\Payroll\Models\PayrollPeriod;
use Modules\Payroll\Models\PayrollRun;

/**
 * أساس الرواتب (Issue #32): إنشاء فترة، ضبط ملف راتب (تاريخي)، وإنشاء مسير.
 * لا حساب هنا (يُبنى في #36). كل عملية مُدقَّقة (Append-Only).
 */
class PayrollService
{
    use RecordsAudit;

    /** إنشاء فترة راتب (فريدة لكل سنة/شهر/فرع). */
    public function createPeriod(array $data, Request $request): PayrollPeriod
    {
        $branchId = $data['branch_id'] ?? null;

        $exists = PayrollPeriod::where('year', $data['year'])
            ->where('month', $data['month'])
            ->where(fn ($q) => $branchId ? $q->where('branch_id', $branchId) : $q->whereNull('branch_id'))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'month' => ['فترة رواتب لهذا الشهر/السنة/الفرع موجودة بالفعل.'],
            ]);
        }

        $period = PayrollPeriod::create([
            'branch_id' => $branchId,
            'year' => $data['year'],
            'month' => $data['month'],
            'status' => 'draft',
            'created_by' => $request->user()?->id,
        ]);

        $this->recordAudit($request, 'payroll_period_created', PayrollPeriod::class, $period->id, [
            'year' => $period->year, 'month' => $period->month, 'branch_id' => $branchId,
        ]);

        return $period;
    }

    /** ضبط ملف راتب نشط للموظف (يؤرشف السابق للحفاظ على التاريخ). */
    public function setSalaryProfile(Employee $employee, array $data, Request $request): EmployeeSalaryProfile
    {
        $effectiveFrom = $data['effective_from'] ?? Carbon::now()->toDateString();

        // معاملة: الأرشفة + الإنشاء عملية ذرّية — يمنع بقاء الموظف بلا ملف راتب نشط
        // (وإسقاطه صامتاً من المسير) إذا فشل الإنشاء بعد الأرشفة.
        $profile = DB::transaction(function () use ($employee, $data, $effectiveFrom, $request) {
            EmployeeSalaryProfile::where('employee_id', $employee->id)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'effective_to' => Carbon::parse($effectiveFrom)->subDay()->toDateString(),
                ]);

            return EmployeeSalaryProfile::create([
                'employee_id' => $employee->id,
                'basic_salary' => $data['basic_salary'],
                'currency' => $data['currency'] ?? 'SAR',
                'payment_method' => $data['payment_method'] ?? 'bank',
                'effective_from' => $effectiveFrom,
                'is_active' => true,
                'created_by' => $request->user()?->id,
            ]);
        });

        $this->recordAudit($request, 'salary_profile_set', EmployeeSalaryProfile::class, $profile->id, [
            'employee_id' => $employee->id, 'basic_salary' => $data['basic_salary'],
        ]);

        return $profile;
    }

    /** إنشاء مسير رواتب لفترة (مسودة). */
    public function createRun(PayrollPeriod $period, array $data, Request $request): PayrollRun
    {
        $run = PayrollRun::create([
            'payroll_period_id' => $period->id,
            'status' => 'draft',
            'notes' => $data['notes'] ?? null,
            'created_by' => $request->user()?->id,
        ]);

        $this->recordAudit($request, 'payroll_run_created', PayrollRun::class, $run->id, [
            'payroll_period_id' => $period->id,
        ]);

        return $run;
    }
}
