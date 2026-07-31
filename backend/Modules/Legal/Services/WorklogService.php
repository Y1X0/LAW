<?php

namespace Modules\Legal\Services;

use Illuminate\Http\Request;
use Modules\Core\Concerns\RecordsAudit;
use Modules\HR\Models\Employee;
use Modules\Legal\Models\DailyWorklog;

/**
 * الإنجاز اليومي الذاتي (Legal / LC-5) — يُكتب/يُحدَّث لليوم فقط (سجل واحد لكل يوم).
 */
class WorklogService
{
    use RecordsAudit;

    public function submitToday(Employee $employee, array $data, Request $request): DailyWorklog
    {
        $log = DailyWorklog::updateOrCreate(
            ['employee_id' => $employee->id, 'work_date' => now()->toDateString()],
            ['done_today' => $data['done_today'], 'plan_tomorrow' => $data['plan_tomorrow'] ?? null],
        );
        $this->recordAudit($request, 'worklog_submitted', DailyWorklog::class, $log->id, ['work_date' => $log->work_date]);

        return $log;
    }
}
