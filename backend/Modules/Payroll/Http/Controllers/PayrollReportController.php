<?php

namespace Modules\Payroll\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Concerns\RecordsAudit;
use Modules\HR\Models\Employee;
use Modules\Payroll\Services\PayrollReportService;

/**
 * تقارير الرواتب (Issue #38). قراءة فقط بصلاحية payroll.view، من النتائج المجمّدة.
 * كل عرض تقرير حسّاس يُسجَّل في التدقيق.
 */
class PayrollReportController
{
    use RecordsAudit;

    public function __construct(private readonly PayrollReportService $service) {}

    /** GET /api/payroll-reports/cost — تقرير تكلفة الرواتب (إجماليات + تجميع). */
    public function cost(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'branch_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'in:draft,processing,completed,approved,paid,locked'],
            'group_by' => ['nullable', 'string', 'in:branch,department,month'],
        ]);

        $report = $this->service->costReport($filters);

        $this->recordAudit($request, 'payroll_cost_report_viewed', 'Modules\Payroll\Reports\CostReport', 0, $report['filters']);

        return response()->json(['data' => $report, 'meta' => null, 'errors' => null]);
    }

    /** GET /api/payroll-reports/employees/{employee} — تاريخ رواتب موظف. */
    public function employee(Request $request, Employee $employee): JsonResponse
    {
        $filters = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'status' => ['nullable', 'string', 'in:draft,processing,completed,approved,paid,locked'],
        ]);

        $report = $this->service->employeeReport($employee, $filters);

        $this->recordAudit($request, 'payroll_employee_report_viewed', Employee::class, $employee->id, $filters);

        return response()->json(['data' => $report, 'meta' => null, 'errors' => null]);
    }
}
