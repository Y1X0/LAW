<?php

namespace Modules\SelfService\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Core\Concerns\RecordsAudit;
use Modules\Payroll\Models\PayrollItem;
use Modules\SelfService\Services\MyPayslipService;

/**
 * كشوف الموظف الذاتية (Issue #49). قراءة/تصدير بصلاحية payslip.view_own،
 * مقيّدة بموظف المستخدم الحالي — لا وصول لكشف موظف آخر (403).
 */
class MyPayslipController
{
    use RecordsAudit;

    public function __construct(private readonly MyPayslipService $service) {}

    /** GET /api/me/payslips — قائمة كشوفي (نهائية فقط). */
    public function index(Request $request): JsonResponse
    {
        $items = $this->service->listFor($request->user()->employee);

        return response()->json(['data' => $items, 'meta' => null, 'errors' => null]);
    }

    /** GET /api/me/payslips/{payroll_item} — كشفي (JSON). */
    public function show(Request $request, PayrollItem $payroll_item): JsonResponse
    {
        $item = $this->service->ownedItem($request->user()->employee, $payroll_item);

        return response()->json(['data' => $this->service->toArray($item), 'meta' => null, 'errors' => null]);
    }

    /** GET /api/me/payslips/{payroll_item}/html — كشفي مطبوعاً (HTML) + تدقيق. */
    public function html(Request $request, PayrollItem $payroll_item): Response
    {
        $item = $this->service->ownedItem($request->user()->employee, $payroll_item);

        $this->recordAudit($request, 'payslip_self_exported', PayrollItem::class, $item->id, [
            'employee_id' => $item->employee_id,
        ]);

        return response($this->service->toHtml($item), 200)
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
