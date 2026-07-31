<?php

namespace Modules\Legal\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * تحقّق إضافة موقع أرشيف (archive.create) — فهرسة فقط.
 */
class StoreArchiveLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file_title' => ['required', 'string', 'max:200'],
            'archive_room' => ['nullable', 'string', 'max:100'],
            'cabinet' => ['nullable', 'string', 'max:50'],
            'shelf' => ['nullable', 'string', 'max:50'],
            'drawer' => ['nullable', 'string', 'max:50'],
            'file_number' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
