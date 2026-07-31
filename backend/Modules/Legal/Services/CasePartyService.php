<?php

namespace Modules\Legal\Services;

use Illuminate\Http\Request;
use Modules\Core\Concerns\RecordsAudit;
use Modules\Legal\Models\CaseParty;
use Modules\Legal\Models\LegalCase;

/**
 * أطراف القضية (Legal / LG-2) — إضافة طرف مرتبط بالقضية + توثيق تلقائي في الخط الزمني.
 */
class CasePartyService
{
    use RecordsAudit;

    public function __construct(private readonly TimelineService $timeline) {}

    public function create(LegalCase $case, array $data, Request $request): CaseParty
    {
        $data['case_id'] = $case->id;
        $data['created_by'] = $request->user()?->id;
        $party = CaseParty::create($data);

        $this->recordAudit($request, 'case_party_added', CaseParty::class, $party->id, ['case_id' => $case->id, 'type' => $party->type]);
        $this->timeline->recordAuto($case, 'party_added', 'تمت إضافة طرف للقضية', $request, "type={$party->type}, name={$party->name}");

        return $party;
    }
}
