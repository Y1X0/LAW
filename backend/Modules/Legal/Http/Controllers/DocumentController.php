<?php

namespace Modules\Legal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Legal\Concerns\AuthorizesCaseAccess;
use Modules\Legal\Http\Requests\StoreDocumentRequest;
use Modules\Legal\Models\CaseDocument;
use Modules\Legal\Models\LegalCase;
use Modules\Legal\Services\DocumentService;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    /**
     * GET /api/documents/{document}/download — بثّ الملف بعد حارس رؤية القضية.
     * لا روابط عامة/موقّعة: نظام صلاحيات Laravel هو المرجع الوحيد. المستندات
     * الوصفية القديمة (بلا ملف) تُعيد 404.
     */
    public function download(Request $request, CaseDocument $document): JsonResponse|StreamedResponse
    {
        if ($denied = $this->guardCaseView($request->user(), $document->case)) {
            return $denied;
        }

        if (! $document->storage_disk || ! $document->storage_path
            || ! Storage::disk($document->storage_disk)->exists($document->storage_path)) {
            return response()->json(['data' => null, 'meta' => null, 'errors' => ['code' => 'NO_FILE']], 404);
        }

        return Storage::disk($document->storage_disk)->download(
            $document->storage_path,
            $document->original_name ?: 'document',
            ['Content-Type' => $document->mime_type ?: 'application/octet-stream'],
        );
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
