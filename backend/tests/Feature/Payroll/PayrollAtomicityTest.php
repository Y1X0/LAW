<?php

namespace Tests\Feature\Payroll;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Mockery;
use Modules\HR\Models\Employee;
use Modules\Payroll\Models\EmployeeSalaryProfile;
use Modules\Payroll\Models\PayrollItem;
use Modules\Payroll\Models\PayrollPeriod;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Services\PayrollCalculationService;
use Tests\TestCase;

/**
 * F6 (تكامل مالي): حساب المسير الدُّفعي ذرّي — فشل موظف في المنتصف يتراجع بالكامل،
 * فلا يبقى مسير محسوب جزئياً يبدو قابلاً للاعتماد.
 */
class PayrollAtomicityTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculate_run_rolls_back_when_an_employee_fails(): void
    {
        $period = PayrollPeriod::create(['year' => 2026, 'month' => 6, 'status' => 'draft']);
        $run = PayrollRun::create(['payroll_period_id' => $period->id, 'status' => 'draft']);

        $e1 = Employee::factory()->create();
        $e2 = Employee::factory()->create();
        foreach ([$e1, $e2] as $e) {
            EmployeeSalaryProfile::create([
                'employee_id' => $e->id, 'basic_salary' => 3000, 'currency' => 'SAR',
                'effective_from' => '2026-01-01', 'is_active' => true,
            ]);
        }

        // بديل جزئي: الموظف الأول يُنشئ بندًا فعليًا، والثاني يرمي استثناءً في منتصف الحلقة.
        $service = Mockery::mock(PayrollCalculationService::class)->makePartial();
        $calls = 0;
        $service->shouldReceive('calculateEmployee')
            ->andReturnUsing(function (PayrollRun $run, Employee $employee, Request $request) use (&$calls) {
                $calls++;
                if ($calls === 1) {
                    PayrollItem::create(['payroll_run_id' => $run->id, 'employee_id' => $employee->id]);

                    return null;
                }

                throw new \RuntimeException('فشل محاكى في منتصف المسير');
            });

        $this->assertSame(0, PayrollItem::where('payroll_run_id', $run->id)->count());

        try {
            $service->calculateRun($run, Request::create('/'));
            $this->fail('كان يجب أن يُرمى الاستثناء من داخل المعاملة.');
        } catch (\RuntimeException) {
            // متوقّع — فشل الموظف الثاني.
        }

        // البند الذي أنشأه الموظف الأول يجب أن يكون قد تراجع بالكامل.
        $this->assertSame(
            0,
            PayrollItem::where('payroll_run_id', $run->id)->count(),
            'يجب أن يتراجع المسير كاملاً عند فشل موظف — لا بنود جزئية تبقى.'
        );
    }
}
