<?php

namespace Modules\CustomFields\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Models\Role;
use Modules\CustomFields\Http\Requests\StoreCustomFieldRequest;
use Modules\CustomFields\Http\Requests\UpdateCustomFieldRequest;
use Modules\CustomFields\Models\CustomFieldDefinition;
use Modules\CustomFields\Services\CustomFieldService;

/**
 * إدارة تعريفات الحقول المخصّصة (Phase 12). محميّ بصلاحية custom_fields.manage.
 * التعريفات فقط — لا يمسّ قيم الكيانات (قضايا/عملاء) بعد.
 */
class CustomFieldController
{
    /** الكيانات المعروضة في البنّاء (قابلة للتوسّع؛ القضايا فقط حاليّاً حتى تُربَط القيم لاحقاً). */
    private const BUILDER_ENTITIES = [
        ['key' => 'case', 'label' => 'القضايا'],
    ];

    /** عناوين الأنواع (عربية) لعرضها في الواجهة — تُشتقّ من CustomFieldDefinition::TYPES. */
    private const TYPE_LABELS = [
        'text' => 'نص', 'longtext' => 'نص طويل', 'number' => 'رقم', 'currency' => 'عملة',
        'date' => 'تاريخ', 'boolean' => 'منطقي (نعم/لا)', 'email' => 'بريد إلكتروني',
        'phone' => 'هاتف', 'url' => 'رابط', 'dropdown' => 'قائمة منسدلة',
    ];

    /** عناوين سياقات العرض. */
    private const CONTEXT_LABELS = [
        'create' => 'الإنشاء', 'edit' => 'التعديل', 'details' => 'التفاصيل', 'list' => 'الجدول',
    ];

    public function __construct(private readonly CustomFieldService $service) {}

    /**
     * بيانات وصفية تُبنى منها الواجهة بالكامل (مصدر الخادم): الكيانات المعروضة، الأنواع،
     * سياقات العرض، والأدوار من قاعدة البيانات — فيظهر دور/نوع جديد بلا تعديل الواجهة.
     */
    public function meta(): JsonResponse
    {
        return $this->ok([
            'entities' => self::BUILDER_ENTITIES,
            'types' => array_map(
                fn (string $t) => ['key' => $t, 'label' => self::TYPE_LABELS[$t] ?? $t],
                CustomFieldDefinition::TYPES,
            ),
            'contexts' => array_map(
                fn (string $c) => ['key' => $c, 'label' => self::CONTEXT_LABELS[$c] ?? $c],
                CustomFieldDefinition::CONTEXTS,
            ),
            'roles' => Role::orderBy('id')->get(['name', 'display_name'])
                ->map(fn (Role $r) => ['id' => $r->name, 'name' => $r->display_name ?: $r->name])
                ->all(),
        ]);
    }

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
