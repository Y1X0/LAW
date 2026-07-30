<?php

namespace Modules\SelfService\Services;

use Illuminate\Http\Request;
use Modules\HR\Models\Employee;
use Modules\Leave\Models\LeaveBalance;
use Modules\Leave\Models\LeaveRequest;
use Modules\Leave\Services\LeaveService;

/**
 * إجازات الموظف الذاتية (Epic 9 / Issue #51).
 *
 * قراءة الرصيد/الطلبات + تقديم طلب **للموظف الحالي فقط** — يعيد استخدام
 * `LeaveService` (#17) للتقديم (لا تكرار لمنطق التحقق/الرصيد). الموظف لا يعتمد
 * ولا يرفض (لا توجد نقاط نهاية لذلك هنا). كل استعلام مقيّد بـ `employee_id`.
 */
class MyLeaveService
{
    public function __construct(private readonly LeaveService $leave) {}

    /** رصيد إجازاتي للسنة الحالية (إجمالي + تفصيل حسب النوع). */
    public function balance(Employee $employee): array
    {
        $balances = LeaveBalance::where('employee_id', $employee->id)
            ->where('year', now()->year)
            ->with('leaveType:id,name')
            ->get();

        return [
            'year' => now()->year,
            'total_remaining' => round((float) $balances->sum('remaining_days'), 1),
            'by_type' => $balances->map(fn (LeaveBalance $b) => [
                'leave_type_id' => $b->leave_type_id,
                'type' => $b->leaveType?->name,
                'entitled' => (float) $b->entitled_days,
                'consumed' => (float) $b->consumed_days,
                'remaining' => (float) $b->remaining_days,
            ])->values()->all(),
        ];
    }

    /** طلباتي (الأحدث أولاً). */
    public function requests(Employee $employee): array
    {
        return LeaveRequest::where('employee_id', $employee->id)
            ->with('leaveType:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (LeaveRequest $r) => [
                'id' => $r->id,
                'type' => $r->leaveType?->name,
                'start_date' => $r->start_date->toDateString(),
                'end_date' => $r->end_date->toDateString(),
                'days' => (float) $r->days,
                'status' => $r->status,
                'reason' => $r->reason,
            ])->all();
    }

    /**
     * تقديم طلب إجازة **باسم الموظف الحالي حصراً** — يعيد استخدام LeaveService.
     * لا يقبل employee_id من المُدخل (يُمنع التقديم لموظف آخر).
     */
    public function submit(Employee $employee, array $data, Request $request): LeaveRequest
    {
        return $this->leave->request($employee, $data, $request);
    }
}
