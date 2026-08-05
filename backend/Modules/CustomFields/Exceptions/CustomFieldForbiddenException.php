<?php

namespace Modules\CustomFields\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * يُرمى عند محاولة تغيير قيمة حقل لا يملك المستخدم صلاحية تعديله (edit_roles). يعرض نفسه
 * برمز واضح خاصّ بالحقل (custom_field_value_forbidden) — لا خطأ عام. رميُه داخل معاملة القضية
 * يُرجِع كل التحديث ذرّياً (لا حفظ جزئي)، مع تسمية الحقل/الحقول الممنوعة بدقّة.
 */
class CustomFieldForbiddenException extends \Exception
{
    /** @param string[] $fields مفاتيح الحقول التي مُنع تعديلها */
    public function __construct(public readonly array $fields)
    {
        parent::__construct('لا تملك صلاحية تعديل بعض الحقول المخصّصة.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'data' => null,
            'meta' => null,
            'errors' => [
                'code' => 'custom_field_value_forbidden',
                'message' => $this->getMessage(),
                'fields' => $this->fields,
            ],
        ], 422);
    }
}
