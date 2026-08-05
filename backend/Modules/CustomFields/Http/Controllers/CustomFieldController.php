<?php

namespace Modules\CustomFields\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CustomFields\Http\Requests\StoreCustomFieldRequest;
use Modules\CustomFields\Http\Requests\UpdateCustomFieldRequest;
use Modules\CustomFields\Models\CustomFieldDefinition;
use Modules\CustomFields\Services\CustomFieldService;

/**
 * إدارة تعريفات الحقول المخصّصة (Phase 12 · PR-1). محميّ بصلاحية custom_fields.manage.
 * هذا PR يدير التعريفات فقط — لا يمسّ قيم الكيانات (قضايا/عملاء) بعد.
 */
class CustomFieldController
{
    public function __construct(private readonly CustomFieldService $service) {}

    public function index(Request $request): JsonResponse
    {
        $entity = $request->query('entity');

        return $this->ok($this->service->list(is_string($entity) ? $entity : null));
    }

    public function store(StoreCustomFieldRequest $request): JsonResponse
    {
        return $this->ok($this->service->create($request->validated(), $request), 201);
    }

    public function show(CustomFieldDefinition $customField): JsonResponse
    {
        return $this->ok($customField);
    }

    public function update(UpdateCustomFieldRequest $request, CustomFieldDefinition $customField): JsonResponse
    {
        return $this->ok($this->service->update($customField, $request->validated(), $request));
    }

    public function destroy(Request $request, CustomFieldDefinition $customField): JsonResponse
    {
        $this->service->delete($customField, $request);

        return $this->ok(['message' => 'تم حذف الحقل المخصّص.']);
    }

    private function ok($data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => null, 'errors' => null], $status);
    }
}
