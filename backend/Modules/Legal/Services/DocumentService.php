<?php

namespace Modules\Legal\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Concerns\RecordsAudit;
use Modules\Legal\Models\CaseDocument;
use Modules\Legal\Models\LegalCase;
use Modules\Legal\Support\DocumentStorage;

/**
 * مستندات القضية (Legal / LC-4 · Phase 5 PR-2) — رفع فعلي إلى R2.
 * الخادم يقرّر القرص والمسار واسم الملف المُخزَّن؛ الاسم الأصلي للعرض فقط.
 */
class DocumentService
{
    use RecordsAudit;

    public function create(LegalCase $case, array $data, Request $request): CaseDocument
    {
        $file = $request->file('file');
        $disk = DocumentStorage::disk();
        // المسار يُشتقّ خادميّاً بالكامل (cases/{id}/{uuid}.{ext}) — لا قيمة من العميل.
        $path = DocumentStorage::pathFor($case->id, $file->getClientOriginalExtension());

        Storage::disk($disk)->putFileAs(dirname($path), $file, basename($path));

        $document = CaseDocument::create([
            'case_id' => $case->id,
            'title' => $data['title'],
            'document_type' => $data['document_type'] ?? null,
            'description' => $data['description'] ?? null,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(), // finfo (محتوى الملف)، لا ترويسة العميل.
            'size_bytes' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
            'storage_disk' => $disk,
            'storage_path' => $path,
            'uploaded_by' => $request->user()?->id,
        ]);

        $this->recordAudit($request, 'case_document_added', CaseDocument::class, $document->id, [
            'case_id' => $case->id,
            'title' => $document->title,
        ]);

        return $document;
    }

    /**
     * حذف المستند وملفه معاً (لتفادي ملفات يتيمة). فحص شامل لليتامى وتحسينات لاحقة
     * في PR-3؛ هنا نضمن ألّا يترك حذفُ الصفّ ملفاً معلّقاً على التخزين.
     */
    public function delete(CaseDocument $document, Request $request): void
    {
        $id = $document->id;
        $caseId = $document->case_id;
        $disk = $document->storage_disk;
        $path = $document->storage_path;

        $document->delete();

        if ($disk && $path && Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }

        $this->recordAudit($request, 'case_document_deleted', CaseDocument::class, $id, ['case_id' => $caseId]);
    }
}
