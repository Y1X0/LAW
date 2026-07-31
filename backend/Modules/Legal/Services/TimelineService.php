<?php

namespace Modules\Legal\Services;

use Illuminate\Http\Request;
use Modules\Core\Concerns\RecordsAudit;
use Modules\Legal\Models\CaseTimelineEvent;
use Modules\Legal\Models\LegalCase;

/**
 * الخط الزمني للقضية (Legal / LC-4) — إضافة فقط (Append-Only).
 * لا تعديل ولا حذف: التصحيح يكون بحدث جديد.
 */
class TimelineService
{
    use RecordsAudit;

    public function append(LegalCase $case, array $data, Request $request): CaseTimelineEvent
    {
        $data['case_id'] = $case->id;
        $data['created_by'] = $request->user()?->id;
        $event = CaseTimelineEvent::create($data);
        $this->recordAudit($request, 'case_timeline_event_added', CaseTimelineEvent::class, $event->id, [
            'case_id' => $case->id,
            'title' => $event->title,
        ]);

        return $event;
    }
}
