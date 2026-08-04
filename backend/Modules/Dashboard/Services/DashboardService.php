<?php

namespace Modules\Dashboard\Services;

use Modules\Finance\Models\Invoice;
use Modules\Finance\Services\ReportService;
use Modules\HR\Models\Employee;
use Modules\Legal\Models\CaseTask;
use Modules\Legal\Models\Client;
use Modules\Legal\Models\Hearing;
use Modules\Legal\Models\LegalCase;

/**
 * تجميع مؤشرات المكتب الشاملة للوحة الإدارة (Dashboard / Phase 7).
 *
 * قراءة فقط عبر نماذج/خدمات الوحدات المُنتِجة — لا كتابة، لا جداول، لا منطق أعمال جديد
 * (نفس نمط MyDashboardService و AdminSummaryController). المجاميع المالية تُعاد استخدامها
 * من ReportService كي لا يتكرّر منطق المحاسبة.
 */
class DashboardService
{
    public function __construct(private readonly ReportService $reports) {}

    public function summary(): array
    {
        return [
            'legal' => $this->legal(),
            'clients' => $this->clients(),
            'hr' => $this->hr(),
            'finance' => $this->finance(),
        ];
    }

    private function legal(): array
    {
        return [
            'cases_total' => LegalCase::count(),
            'cases_open' => LegalCase::where('status', 'open')->count(),
            'cases_closed' => LegalCase::where('status', 'closed')->count(),
            'hearings_upcoming' => Hearing::where('status', 'scheduled')->where('scheduled_at', '>=', now())->count(),
            'tasks_overdue' => CaseTask::where('status', 'open')
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', now()->toDateString())
                ->count(),
        ];
    }

    private function clients(): array
    {
        return [
            'active' => Client::where('status', 'active')->count(),
            'total' => Client::count(),
        ];
    }

    private function hr(): array
    {
        return [
            'employees_active' => Employee::where('status', 'active')->count(),
        ];
    }

    private function finance(): array
    {
        $pnl = $this->reports->incomeExpense(null, null);

        return [
            'outstanding' => $this->reports->receivables()['total_outstanding'],
            'revenue' => $pnl['revenue'],
            'expenses' => $pnl['expenses'],
            'net' => $pnl['net'],
            'invoices_overdue' => Invoice::where('status', 'overdue')->count(),
        ];
    }
}
