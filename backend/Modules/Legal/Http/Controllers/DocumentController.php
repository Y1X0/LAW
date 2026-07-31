<?php

namespace Modules\Legal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Legal\Concerns\AuthorizesCaseAccess;
use Modules\Legal\Http\Requests\StoreDocumentRequest;
use Modules\Legal\Models\CaseDocument;
use Modules\Legal\Models\LegalCase;
use Modules\Legal\Services\DocumentService;

/**
 * مستندات القضية (Legal / LC-4) — بيانات وصفية فقط.
 * القراءة ترث عزل القضية؛ الإضافة documents.upload؛ الحذف documents.delete.
 */
class DocumentController
{
    use AuthorizesCaseAccess;

    public function __construct(private readonly DocumentService $service) {}

    /** GET /api/cases/{case}/documents — مستندات القضية (بحارس رؤية القضية). */
    public function index(Request $request, LegalCase $case): JsonResponse
    {
        if ($denied = $this->guardCaseView($request->user(), $case)) {
            return $denied;
        }

        return $this->ok($case->documents()->orderByDesc('id')->get());
    }

    /** POST /api/cases/{case}/documents — إضافة مستند (metadata فقط). */
    public function store(StoreDocumentRequest $request, LegalCase $case): JsonResponse
    {
        if ($denied = $this->guardCaseView($request->user(), $case)) {
            return $denied;
        }
        $document = $this->service->create($case, $request->validated(), $request);

        return $this->ok($document, 201);
    }

    /** DELETE /api/documents/{document} — حذف مستند (بصلاحية documents.delete + رؤية القضية). */
    public function destroy(Request $request, CaseDocument $document): JsonResponse
    {
        if ($denied = $this->guardCaseView($request->user(), $document->case)) {
            return $denied;
        }
        $this->service->delete($document, $request);

        return $this->ok(['message' => 'تم حذف المستند.']);
    }

    private function ok($data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => null, 'errors' => null], $status);
    }
}
