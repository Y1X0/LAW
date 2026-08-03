<?php

namespace Modules\Legal\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Concerns\RecordsAudit;
use Modules\Legal\Models\CaseDocument;
use Modules\Legal\Models\LegalCase;
use Modules\Legal\Support\DocumentStorage;
use Throwable;

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

        $checksum = hash_file('sha256', $file->getRealPath());
        $originalName = $file->getClientOriginalName();
        $mime = $file->getMimeType(); // finfo (محتوى الملف)، لا ترويسة العميل.
        $size = $file->getSize();

        Storage::disk($disk)->putFileAs(dirname($path), $file, basename($path));

        try {
            $document = CaseDocument::create([
                'case_id' => $case->id,
                'title' => $data['title'],
                'document_type' => $data['document_type'] ?? null,
                'description' => $data['description'] ?? null,
                'original_name' => $originalName,
                'mime_type' => $mime,
                'size_bytes' => $size,
                'checksum' => $checksum,
                'storage_disk' => $disk,
                'storage_path' => $path,
                'uploaded_by' => $request->user()?->id,
            ]);
        } catch (Throwable $e) {
            // فشل إنشاء الصفّ بعد رفع الملف → احذف الملف كي لا يبقى يتيماً.
            Storage::disk($disk)->delete($path);
            throw $e;
        }

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

        // حذف الملف بأفضل جهد: لو تعذّر (تعطّل التخزين) لا نُفشل حذف الصفّ — يبقى ملف
        // يتيم يلتقطه أمر legal:documents:prune-orphans لاحقاً بدل خطأ 500 للمستخدم.
        if ($disk && $path) {
            try {
                Storage::disk($disk)->delete($path);
            } catch (Throwable $e) {
                Log::warning('تعذّر حذف ملف المستند من التخزين؛ سيُعالَج كيتيم.', [
                    'document_id' => $id, 'disk' => $disk, 'path' => $path, 'error' => $e->getMessage(),
                ]);
            }
        }

        $this->recordAudit($request, 'case_document_deleted', CaseDocument::class, $id, ['case_id' => $caseId]);
    }
}
