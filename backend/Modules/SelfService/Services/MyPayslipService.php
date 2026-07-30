<?php

namespace Modules\SelfService\Services;

use Modules\HR\Models\Employee;
use Modules\Payroll\Models\PayrollItem;
use Modules\Payroll\Services\PayslipService;

/**
 * كشوف الموظف الذاتية (Epic 9 / Issue #49).
 *
 * يعرض كشوف الموظف الحالي **فقط** من مسيّرات **نهائية** (approved/paid/locked) —
 * لا مسوّدات ولا كشوف موظف آخر. يعيد استخدام PayslipService (#37) للعرض دون إعادة حساب.
 */
class MyPayslipService
{
    /** حالات المسير النهائية التي يظهر كشفها للموظف. */
    private const FINALIZED = ['approved', 'paid', 'locked'];

    public function __construct(private readonly PayslipService $payslips) {}

    /** قائمة كشوفي (من مسيّرات نهائية فقط). */
    public function listFor(Employee $employee): array
    {
        return PayrollItem::query()
            ->join('payroll_runs', 'payroll_runs.id', '=', 'payroll_items.payroll_run_id')
            ->join('payroll_periods', 'payroll_periods.id', '=', 'payroll_runs.payroll_period_id')
            ->where('payroll_items.employee_id', $employee->id)
            ->whereIn('payroll_runs.status', self::FINALIZED)
            ->orderByDesc('payroll_periods.year')
            ->orderByDesc('payroll_periods.month')
            ->get([
                'payroll_items.id',
                'payroll_items.gross_amount',
                'payroll_items.deductions_total',
                'payroll_items.net_amount',
                'payroll_items.currency',
                'payroll_periods.year',
                'payroll_periods.month',
                'payroll_runs.status as run_status',
            ])
            ->map(fn (PayrollItem $i) => [
                'payroll_item_id' => $i->id,
                'year' => (int) $i->year,
                'month' => (int) $i->month,
                'status' => $i->run_status,
                'gross' => (float) $i->gross_amount,
                'deductions_total' => (float) $i->deductions_total,
                'net' => (float) $i->net_amount,
                'currency' => $i->currency,
            ])->all();
    }

    /** يتحقق أن الكشف يخصّ الموظف الحالي وأن مسيره نهائي — وإلا 403. */
    public function ownedItem(Employee $employee, PayrollItem $item): PayrollItem
    {
        abort_unless($item->employee_id === $employee->id, 403, 'لا يمكنك الوصول لكشف موظف آخر.');

        $item->loadMissing('run:id,status');
        abort_unless(in_array($item->run?->status, self::FINALIZED, true), 403, 'الكشف غير متاح (المسير غير نهائي).');

        return $item;
    }

    public function toArray(PayrollItem $item): array
    {
        return $this->payslips->toArray($item);
    }

    public function toHtml(PayrollItem $item): string
    {
        return $this->payslips->toHtml($item);
    }
}
