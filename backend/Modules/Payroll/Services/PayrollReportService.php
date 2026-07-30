<?php

namespace Modules\Payroll\Services;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Department;
use Modules\HR\Models\Employee;
use Modules\Payroll\Models\PayrollItem;

/**
 * تقارير الرواتب (Epic 8 / Issue #38).
 *
 * **مبدأ النزاهة التاريخية:** يقرأ حصراً من نتائج المسير المجمّدة
 * (`payroll_items` ← `payroll_runs` ← `payroll_periods`) — كلها مملوكة لوحدة Payroll.
 * لا يقرأ إطلاقاً من المصادر الحيّة (attendance_records / leave_requests /
 * employee_salary_components / ملفات الرواتب النشطة)، لذا تبقى تقارير المسيّرات
 * المعتمدة/المقفلة ثابتة حتى لو تغيّرت البيانات الحيّة لاحقاً.
 */
class PayrollReportService
{
    /** أبعاد التجميع المسموحة لتقرير التكلفة. */
    public const GROUP_BY = ['branch', 'department', 'month'];

    /**
     * تقرير تكلفة الرواتب: إجماليات + تفصيل مجمّع حسب الفرع/القسم/الشهر.
     *
     * @param  array{year?:int|null, month?:int|null, branch_id?:int|null, department_id?:int|null, status?:string|null, group_by?:string|null}  $filters
     */
    public function costReport(array $filters): array
    {
        $groupBy = in_array($filters['group_by'] ?? null, self::GROUP_BY, true)
            ? $filters['group_by']
            : 'branch';

        $totals = $this->aggregate($this->baseQuery($filters))->first();

        return [
            'filters' => [
                'year' => $filters['year'] ?? null,
                'month' => $filters['month'] ?? null,
                'branch_id' => $filters['branch_id'] ?? null,
                'department_id' => $filters['department_id'] ?? null,
                'status' => $filters['status'] ?? null,
                'group_by' => $groupBy,
            ],
            'totals' => $this->totalsPayload($totals),
            'groups' => $this->groups($filters, $groupBy),
        ];
    }

    /**
     * تقرير موظف: تاريخ رواتبه عبر المسيّرات (من النتائج المجمّدة فقط).
     *
     * @param  array{year?:int|null, status?:string|null}  $filters
     */
    public function employeeReport(Employee $employee, array $filters): array
    {
        $rows = $this->baseQuery(['employee_id' => $employee->id] + $filters)
            ->orderByDesc('payroll_periods.year')
            ->orderByDesc('payroll_periods.month')
            ->get([
                'payroll_items.id',
                'payroll_items.currency',
                'payroll_items.basic_salary',
                'payroll_items.allowances_total',
                'payroll_items.deductions_total',
                'payroll_items.gross_amount',
                'payroll_items.net_amount',
                'payroll_periods.year',
                'payroll_periods.month',
                'payroll_runs.status as run_status',
            ]);

        $history = $rows->map(fn (PayrollItem $i) => [
            'payroll_item_id' => $i->id,
            'year' => (int) $i->year,
            'month' => (int) $i->month,
            'status' => $i->run_status,
            'currency' => $i->currency,
            'basic' => (float) $i->basic_salary,
            'allowances' => (float) $i->allowances_total,
            'deductions' => (float) $i->deductions_total,
            'gross' => (float) $i->gross_amount,
            'net' => (float) $i->net_amount,
        ])->all();

        return [
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->full_name_ar,
                'employee_number' => $employee->employee_no,
            ],
            'filters' => ['year' => $filters['year'] ?? null, 'status' => $filters['status'] ?? null],
            'totals' => [
                'runs' => count($history),
                'gross' => round(array_sum(array_column($history, 'gross')), 2),
                'deductions' => round(array_sum(array_column($history, 'deductions')), 2),
                'net' => round(array_sum(array_column($history, 'net')), 2),
            ],
            'history' => $history,
        ];
    }

    /**
     * الاستعلام الأساس — يُقيّد على النتائج المجمّدة فقط (لا مصادر حيّة).
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<PayrollItem>
     */
    private function baseQuery(array $filters): Builder
    {
        return PayrollItem::query()
            ->join('payroll_runs', 'payroll_runs.id', '=', 'payroll_items.payroll_run_id')
            ->join('payroll_periods', 'payroll_periods.id', '=', 'payroll_runs.payroll_period_id')
            ->when($filters['year'] ?? null, fn (Builder $q, $y) => $q->where('payroll_periods.year', $y))
            ->when($filters['month'] ?? null, fn (Builder $q, $m) => $q->where('payroll_periods.month', $m))
            ->when($filters['branch_id'] ?? null, fn (Builder $q, $b) => $q->where('payroll_items.branch_id', $b))
            ->when($filters['department_id'] ?? null, fn (Builder $q, $d) => $q->where('payroll_items.department_id', $d))
            ->when($filters['status'] ?? null, fn (Builder $q, $s) => $q->where('payroll_runs.status', $s))
            ->when($filters['employee_id'] ?? null, fn (Builder $q, $e) => $q->where('payroll_items.employee_id', $e));
    }

    /**
     * إجماليات (SUM/COUNT) محمولة عبر SQLite/PostgreSQL.
     *
     * @param  Builder<PayrollItem>  $query
     * @return Builder<PayrollItem>
     */
    private function aggregate(Builder $query): Builder
    {
        return $query->selectRaw(
            'COUNT(DISTINCT payroll_items.employee_id) as headcount, '.
            'COUNT(DISTINCT payroll_items.payroll_run_id) as runs, '.
            'COALESCE(SUM(payroll_items.basic_salary), 0) as basic, '.
            'COALESCE(SUM(payroll_items.allowances_total), 0) as allowances, '.
            'COALESCE(SUM(payroll_items.deductions_total), 0) as deductions, '.
            'COALESCE(SUM(payroll_items.gross_amount), 0) as gross, '.
            'COALESCE(SUM(payroll_items.net_amount), 0) as net'
        );
    }

    /** @param  array<string, mixed>  $filters */
    private function groups(array $filters, string $groupBy): array
    {
        if ($groupBy === 'month') {
            return $this->monthGroups($filters);
        }

        $column = $groupBy === 'department' ? 'payroll_items.department_id' : 'payroll_items.branch_id';

        $rows = $this->aggregate($this->baseQuery($filters))
            ->addSelect($column.' as group_key')
            ->groupBy($column)
            ->get();

        $labels = $this->labels($groupBy, $rows->pluck('group_key')->filter()->all());

        return $rows->map(fn ($r) => [
            'key' => $r->group_key !== null ? (int) $r->group_key : null,
            'label' => $r->group_key !== null ? ($labels[$r->group_key] ?? null) : 'غير محدد',
        ] + $this->totalsPayload($r))->all();
    }

    /** @param  array<string, mixed>  $filters */
    private function monthGroups(array $filters): array
    {
        $rows = $this->aggregate($this->baseQuery($filters))
            ->addSelect(['payroll_periods.year as g_year', 'payroll_periods.month as g_month'])
            ->groupBy('payroll_periods.year', 'payroll_periods.month')
            ->orderBy('payroll_periods.year')
            ->orderBy('payroll_periods.month')
            ->get();

        return $rows->map(fn ($r) => [
            'key' => ['year' => (int) $r->g_year, 'month' => (int) $r->g_month],
            'label' => sprintf('%02d/%d', $r->g_month, $r->g_year),
        ] + $this->totalsPayload($r))->all();
    }

    /**
     * تسمية مقروءة للفرع/القسم عبر نماذجها (قراءة هوية فقط، دفعة واحدة).
     *
     * @param  array<int, mixed>  $ids
     * @return array<int, string>
     */
    private function labels(string $groupBy, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $model = $groupBy === 'department' ? Department::class : Branch::class;

        return $model::whereIn('id', $ids)->pluck('name', 'id')->all();
    }

    /** حمولة موحّدة للإجماليات (تقريب لخانتين، أرقام حقيقية). */
    private function totalsPayload(object $row): array
    {
        return [
            'headcount' => (int) ($row->headcount ?? 0),
            'runs' => (int) ($row->runs ?? 0),
            'basic' => round((float) ($row->basic ?? 0), 2),
            'allowances' => round((float) ($row->allowances ?? 0), 2),
            'deductions' => round((float) ($row->deductions ?? 0), 2),
            'gross' => round((float) ($row->gross ?? 0), 2),
            'net' => round((float) ($row->net ?? 0), 2),
        ];
    }
}
