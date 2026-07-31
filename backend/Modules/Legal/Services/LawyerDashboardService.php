<?php

namespace Modules\Legal\Services;

use Modules\HR\Models\Employee;
use Modules\Legal\Models\CaseAssignment;
use Modules\Legal\Models\CaseTask;
use Modules\Legal\Models\CaseTimelineEvent;
use Modules\Legal\Models\DailyWorklog;
use Modules\Legal\Models\Hearing;
use Modules\Legal\Models\LegalCase;

/**
 * تجميع لوحة المحامي (Legal / LG-1) — قراءة فقط، مُنطَّقة على قضايا المحامي المسندة.
 * نداء واحد يغذّي أول شاشة في واجهة المحامي (بدل 5–6 نداءات).
 */
class LawyerDashboardService
{
    public function summary(Employee $employee): array
    {
        $caseIds = CaseAssignment::where('employee_id', $employee->id)->pluck('case_id');

        $byStatus = LegalCase::whereIn('id', $caseIds)
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $nextHearing = Hearing::whereIn('case_id', $caseIds)
            ->upcoming()
            ->with('case:id,internal_number,title')
            ->orderBy('scheduled_at')
            ->first();

        $recentEvents = CaseTimelineEvent::whereIn('case_id', $caseIds)
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'case_id', 'title', 'event_date']);

        $lastWorklog = DailyWorklog::where('employee_id', $employee->id)
            ->orderByDesc('work_date')
            ->first(['id', 'work_date', 'done_today']);

        return [
            'cases' => [
                'total' => $caseIds->count(),
                'open' => (int) ($byStatus['open'] ?? 0),
                'pending' => (int) ($byStatus['pending'] ?? 0),
                'closed' => (int) ($byStatus['closed'] ?? 0),
            ],
            'tasks' => [
                'pending' => CaseTask::where('assigned_to', $employee->id)->where('status', 'open')->count(),
            ],
            'next_hearing' => $nextHearing,
            'recent_events' => $recentEvents,
            'last_worklog' => $lastWorklog,
        ];
    }
}
