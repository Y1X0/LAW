<?php

namespace Modules\Payroll\Services;

use Illuminate\Support\Str;
use Modules\Payroll\Models\PayrollItem;

/**
 * توليد كشف الراتب (Issue #37) من نتيجة الحساب المجمّدة (payroll_item).
 * لا يعيد الحساب — يعرض الأرقام المحفوظة فقط. JSON + HTML مكتفٍ ذاتياً للطباعة.
 */
class PayslipService
{
    /** التسميات المقروءة لبنود معروفة. */
    private const LABELS = [
        'basic' => 'Basic Salary',
        'overtime' => 'Overtime',
        'absence' => 'Absence',
        'late' => 'Late',
        'unpaid_leave' => 'Unpaid Leave',
    ];

    /** بيانات كشف الراتب (JSON) من النتيجة المحفوظة. */
    public function toArray(PayrollItem $item): array
    {
        $item->loadMissing(['employee:id,full_name_ar,employee_no', 'run:id,payroll_period_id,status', 'run.period:id,year,month']);
        $breakdown = $item->breakdown ?? ['earnings' => [], 'deductions' => []];

        return [
            'employee' => [
                'name' => $item->employee?->full_name_ar,
                'employee_number' => $item->employee?->employee_no,
            ],
            'period' => [
                'year' => $item->run?->period?->year,
                'month' => $item->run?->period?->month,
            ],
            'currency' => $item->currency,
            'earnings' => $this->lines($breakdown['earnings'] ?? []),
            'deductions' => $this->lines($breakdown['deductions'] ?? []),
            'gross' => (float) $item->gross_amount,
            'deductions_total' => (float) $item->deductions_total,
            'net' => (float) $item->net_amount,
            'status' => $item->run?->status,
        ];
    }

    /** كشف راتب كمستند HTML مكتفٍ ذاتياً (للطباعة → PDF من المتصفح). */
    public function toHtml(PayrollItem $item): string
    {
        $p = $this->toArray($item);
        $rows = fn (array $lines) => implode('', array_map(
            fn ($l) => '<tr><td>'.e($l['name']).'</td><td class="amt">'.number_format($l['amount'], 2).'</td></tr>',
            $lines
        ));

        $name = e($p['employee']['name'] ?? '');
        $no = e($p['employee']['employee_number'] ?? '');
        $period = e(sprintf('%02d/%d', $p['period']['month'] ?? 0, $p['period']['year'] ?? 0));
        $cur = e($p['currency']);

        return <<<HTML
<!DOCTYPE html>
<html lang="ar" dir="rtl"><head><meta charset="UTF-8"><title>كشف راتب — {$name}</title>
<style>
body{font-family:sans-serif;max-width:640px;margin:24px auto;color:#222}
h1{font-size:20px;border-bottom:2px solid #333;padding-bottom:8px}
.meta{margin:12px 0;font-size:14px}
table{width:100%;border-collapse:collapse;margin:10px 0}
th,td{padding:6px 8px;border-bottom:1px solid #ddd;text-align:right}
.amt{text-align:left;font-variant-numeric:tabular-nums}
.sec{margin-top:16px;font-weight:bold}
.total{font-weight:bold;border-top:2px solid #333}
</style></head><body>
<h1>كشف راتب / Payslip</h1>
<div class="meta">الموظف: <b>{$name}</b> ({$no}) &middot; الفترة: <b>{$period}</b> &middot; العملة: {$cur}</div>
<div class="sec">المستحقات / Earnings</div>
<table>{$rows($p['earnings'])}<tr class="total"><td>الإجمالي / Gross</td><td class="amt">{$this->fmt($p['gross'])}</td></tr></table>
<div class="sec">الخصومات / Deductions</div>
<table>{$rows($p['deductions'])}<tr class="total"><td>إجمالي الخصومات</td><td class="amt">{$this->fmt($p['deductions_total'])}</td></tr></table>
<table><tr class="total"><td>صافي الراتب / Net Salary</td><td class="amt">{$this->fmt($p['net'])} {$cur}</td></tr></table>
<div class="meta">الحالة / Status: {$p['status']}</div>
</body></html>
HTML;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array{code:string, name:string, amount:float}>
     */
    private function lines(array $lines): array
    {
        return array_map(fn ($l) => [
            'code' => $l['code'],
            'name' => $this->label($l['code']),
            'amount' => (float) $l['amount'],
        ], $lines);
    }

    private function label(string $code): string
    {
        if (isset(self::LABELS[$code])) {
            return self::LABELS[$code];
        }

        // مكوّنات: "allowance:housing" / "deduction:loan" → عنوان مقروء.
        $name = Str::contains($code, ':') ? Str::after($code, ':') : $code;

        return Str::of($name)->replace('_', ' ')->title()->value();
    }

    private function fmt(float $value): string
    {
        return number_format($value, 2);
    }
}
