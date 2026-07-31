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

    /**
     * تسجيل حدث تلقائي بمفتاح آلي (event_type) — يُستدعى من إجراءات المجال
     * (إنشاء/إسناد/جلسة/تأجيل/إغلاق/إضافة طرف). التدقيق يتكفّل به الإجراء الأصلي.
     */
    public function recordAuto(LegalCase $case, string $eventType, string $title, Request $request, ?string $description = null): CaseTimelineEvent
    {
        return CaseTimelineEvent::create([
            'case_id' => $case->id,
            'title' => $title,
            'event_type' => $eventType,
            'event_date' => now()->toDateString(),
            'description' => $description,
            'created_by' => $request->user()?->id,
        ]);
    }
}
