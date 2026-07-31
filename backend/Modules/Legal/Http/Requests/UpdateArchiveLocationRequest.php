<?php

namespace Modules\Legal\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * تحقّق تعديل موقع أرشيف (archive.update).
 */
class UpdateArchiveLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file_title' => ['sometimes', 'required', 'string', 'max:200'],
            'archive_room' => ['sometimes', 'nullable', 'string', 'max:100'],
            'cabinet' => ['sometimes', 'nullable', 'string', 'max:50'],
            'shelf' => ['sometimes', 'nullable', 'string', 'max:50'],
            'drawer' => ['sometimes', 'nullable', 'string', 'max:50'],
            'file_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
