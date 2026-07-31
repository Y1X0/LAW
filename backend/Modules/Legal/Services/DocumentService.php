<?php

namespace Modules\Legal\Services;

use Illuminate\Http\Request;
use Modules\Core\Concerns\RecordsAudit;
use Modules\Legal\Models\CaseDocument;
use Modules\Legal\Models\LegalCase;

/**
 * مستندات القضية (Legal / LC-4) — بيانات وصفية فقط (بلا رفع فعلي).
 */
class DocumentService
{
    use RecordsAudit;

    public function create(LegalCase $case, array $data, Request $request): CaseDocument
    {
        $data['case_id'] = $case->id;
        $data['uploaded_by'] = $request->user()?->id;
        $document = CaseDocument::create($data);
        $this->recordAudit($request, 'case_document_added', CaseDocument::class, $document->id, [
            'case_id' => $case->id,
            'title' => $document->title,
        ]);

        return $document;
    }

    public function delete(CaseDocument $document, Request $request): void
    {
        $id = $document->id;
        $caseId = $document->case_id;
        $document->delete();
        $this->recordAudit($request, 'case_document_deleted', CaseDocument::class, $id, ['case_id' => $caseId]);
    }
}
