<?php

namespace Modules\Legal\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * تحقّق رفع مستند (documents.upload) — Phase 5 · PR-2 (رفع فعلي إلى R2).
 *
 * P0 أمني: لا يُقبل `storage_disk`/`storage_path` من العميل — الخادم يقرّرهما.
 * قرار قبول الملف مبنيّ على **محتواه** لا على Content-Type القادم من العميل:
 * قاعدة `mimes` في Laravel تخمّن النوع من الملف نفسه (finfo) لا من ترويسة الطلب،
 * ضمن قائمة بيضاء صارمة (PDF/صور/Word) وحدٍّ أقصى 20MB.
 */
class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
            'title' => ['required', 'string', 'max:200'],
            'document_type' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.max' => 'الحد الأقصى لحجم الملف 20 ميغابايت.',
            'file.mimes' => 'نوع الملف غير مسموح — المسموح: PDF أو صورة (JPG/PNG) أو Word (DOC/DOCX).',
        ];
    }
}
