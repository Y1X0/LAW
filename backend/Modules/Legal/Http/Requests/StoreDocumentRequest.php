<?php

namespace Modules\Legal\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * تحقّق إضافة مستند (documents.upload).
 *
 * P0 أمني (Phase 5): لا يُقبل `storage_disk`/`storage_path`/`disk`/`path` من العميل
 * إطلاقاً — الخادم وحده يقرّر القرص والمسار واسم الملف عند الرفع (PR-2). قبول أيّ
 * منها كان يفتح Path-Injection/IDOR. هذه القواعد تقبل البيانات الوصفية فقط.
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
            'title' => ['required', 'string', 'max:200'],
            'document_type' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
