<?php

namespace Modules\Legal\Services;

use Illuminate\Http\Request;
use Modules\Core\Concerns\RecordsAudit;
use Modules\Legal\Models\CaseArchiveLocation;
use Modules\Legal\Models\LegalCase;

/**
 * فهرسة الأرشيف الورقي (Legal / LG-3) — عدّة مواقع لكل قضية، بلا رفع ملفات.
 */
class ArchiveService
{
    use RecordsAudit;

    public function create(LegalCase $case, array $data, Request $request): CaseArchiveLocation
    {
        $data['case_id'] = $case->id;
        $data['created_by'] = $request->user()?->id;
        $location = CaseArchiveLocation::create($data);
        $this->recordAudit($request, 'archive_location_added', CaseArchiveLocation::class, $location->id, ['case_id' => $case->id]);

        return $location;
    }

    public function update(CaseArchiveLocation $location, array $data, Request $request): CaseArchiveLocation
    {
        $location->update($data);
        $this->recordAudit($request, 'archive_location_updated', CaseArchiveLocation::class, $location->id);

        return $location;
    }

    public function delete(CaseArchiveLocation $location, Request $request): void
    {
        $id = $location->id;
        $caseId = $location->case_id;
        $location->delete();
        $this->recordAudit($request, 'archive_location_deleted', CaseArchiveLocation::class, $id, ['case_id' => $caseId]);
    }
}
