<?php

namespace Modules\Core\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Modules\Core\Models\AuditLog;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\HR\Models\Employee;
use Modules\Leave\Models\LeaveRequest;
use Modules\Legal\Models\Hearing;
use Modules\Legal\Models\LegalCase;

/**
 * لوحة نظام وحدة التحكّم (ADMIN-4) — تجميع إحصاءات المنصّة للقراءة فقط.
 * نقطة جديدة محميّة بصلاحية users.manage (لا تغيّر أي مسار قائم، ولا Migration).
 * القراءة فقط — لا تعدّل أي بيانات.
 */
class AdminSummaryController
{
    /** GET /api/admin/summary */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => [
                'users' => $this->users(),
                'employees' => $this->employees(),
                'rbac' => [
                    'roles' => Role::count(),
                    'permissions' => Permission::count(),
                ],
                'legal' => $this->legal(),
                'hr' => [
                    'leave_pending' => LeaveRequest::where('status', 'pending')->count(),
                ],
                'activity' => $this->activity(),
            ],
            'meta' => null,
            'errors' => null,
        ]);
    }

    /** إحصاء المستخدمين حسب الحالة + من بلا أدوار (تنبيه). */
    private function users(): array
    {
        $byStatus = User::query()
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        return [
            'total' => (int) $byStatus->sum(),
            'active' => (int) ($byStatus['active'] ?? 0),
            'suspended' => (int) ($byStatus['suspended'] ?? 0),
            'locked' => (int) ($byStatus['locked'] ?? 0),
            'without_roles' => User::whereDoesntHave('roles')->count(),
        ];
    }

    /** إحصاء الموظفين حسب الحالة. */
    private function employees(): array
    {
        $byStatus = Employee::query()
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        return [
            'total' => (int) $byStatus->sum(),
            'active' => (int) ($byStatus['active'] ?? 0),
            'on_leave' => (int) ($byStatus['on_leave'] ?? 0),
            'suspended' => (int) ($byStatus['suspended'] ?? 0),
            'terminated' => (int) ($byStatus['terminated'] ?? 0),
        ];
    }

    /** إحصاء المجال القانوني (قضايا/جلسات قادمة). */
    private function legal(): array
    {
        $byStatus = LegalCase::query()
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        return [
            'cases_total' => (int) $byStatus->sum(),
            'cases_open' => (int) ($byStatus['open'] ?? 0),
            'cases_closed' => (int) ($byStatus['closed'] ?? 0),
            'hearings_upcoming' => Hearing::where('status', 'scheduled')
                ->where('scheduled_at', '>=', now())
                ->count(),
        ];
    }

    /** آخر أحداث التدقيق (نشاط النظام). */
    private function activity(): array
    {
        return AuditLog::query()
            ->latest('id')
            ->limit(10)
            ->get(['id', 'user_id', 'action', 'auditable_type', 'created_at'])
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'user_id' => $log->user_id,
                'action' => $log->action,
                'auditable_type' => class_basename((string) $log->auditable_type),
                'created_at' => optional($log->created_at)->toIso8601String(),
            ])
            ->all();
    }
}
