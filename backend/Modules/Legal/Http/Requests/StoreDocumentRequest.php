<?php

namespace Modules\Legal\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * تحقّق إضافة مستند (documents.upload) — بيانات وصفية فقط، بلا رفع ملف فعلي.
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
            'storage_disk' => ['nullable', 'string', 'max:50'],
            'storage_path' => ['nullable', 'string', 'max:500'],
        ];
    }
}
