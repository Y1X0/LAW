<?php

namespace Modules\Legal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Legal\Concerns\AuthorizesCaseAccess;
use Modules\Legal\Http\Requests\StoreTimelineEventRequest;
use Modules\Legal\Models\LegalCase;
use Modules\Legal\Services\TimelineService;

/**
 * الخط الزمني للقضية (Legal / LC-4) — Append-Only.
 * القراءة ترث عزل القضية؛ الإضافة تحت cases.update. لا نقاط تعديل/حذف.
 */
class TimelineController
{
    use AuthorizesCaseAccess;

    public function __construct(private readonly TimelineService $service) {}

    /** GET /api/cases/{case}/timeline — أحداث القضية زمنياً (بحارس رؤية القضية). */
    public function index(Request $request, LegalCase $case): JsonResponse
    {
        if ($denied = $this->guardCaseView($request->user(), $case)) {
            return $denied;
        }

        $events = $case->timelineEvents()->orderBy('event_date')->orderBy('id')->get();

        return $this->ok($events);
    }

    /** POST /api/cases/{case}/timeline — إضافة حدث (لا يُعدَّل لاحقاً). */
    public function store(StoreTimelineEventRequest $request, LegalCase $case): JsonResponse
    {
        // ملاحظة: الإضافة محميّة بصلاحية الإدارة cases.update (مسار)، ولا تُقيَّد برؤية
        // القضية عمداً — يتّسق مع العقد القائم (حامل cases.update يديـر أي قضية).
        $event = $this->service->append($case, $request->validated(), $request);

        return $this->ok($event, 201);
    }

    private function ok($data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => null, 'errors' => null], $status);
    }
}
