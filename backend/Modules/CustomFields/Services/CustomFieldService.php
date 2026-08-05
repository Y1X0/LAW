<?php

namespace Modules\CustomFields\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Core\Concerns\RecordsAudit;
use Modules\CustomFields\Models\CustomFieldDefinition;
use Modules\CustomFields\Models\CustomFieldValue;

/**
 * إدارة تعريفات الحقول المخصّصة (Phase 12 · PR-1). CRUD مع تدقيق لكل عملية — تعريف الحقل
 * ذاته حدثٌ يُسجَّل (نظام قانوني). ربط القيم بالكيانات وتدقيق تغيّرها يأتي في PR لاحق.
 */
class CustomFieldService
{
    use RecordsAudit;

    /** قائمة التعريفات (اختيارياً لكيان)، مرتّبة بالكيان ثم ترتيب العرض. */
    public function list(?string $entity = null): Collection
    {
        return CustomFieldDefinition::query()
            ->when($entity !== null && $entity !== '', fn ($q) => $q->where('entity', $entity))
            ->orderBy('entity')->orderBy('sort_order')->orderBy('id')
            ->get();
    }

    public function create(array $data, Request $request): CustomFieldDefinition
    {
        $data['created_by'] = $request->user()?->id;
        $definition = CustomFieldDefinition::create($data);

        $this->recordAudit($request, 'custom_field_defined', CustomFieldDefinition::class, $definition->id, [
            'entity' => $definition->entity, 'key' => $definition->key, 'type' => $definition->type,
        ]);

        return $definition;
    }

    /** يحدّث تعريفاً. entity/key غير قابلين للتغيير (يُستبعدان في الطلب) لتفادي يُتْم القيم لاحقاً. */
    public function update(CustomFieldDefinition $definition, array $data, Request $request): CustomFieldDefinition
    {
        $data['updated_by'] = $request->user()?->id;
        $definition->update($data);

        $this->recordAudit($request, 'custom_field_updated', CustomFieldDefinition::class, $definition->id, $data);

        return $definition;
    }

    /**
     * يحذف تعريفاً. يُمنَع الحذف إن كان للحقل قيم مسجّلة (بيانات قانونية لا تُمحى) — يُعطَّل بدلاً
     * منه. لا cascade على القيم (القيد restrict على مستوى القاعدة يضمن ذلك أيضاً).
     */
    public function delete(CustomFieldDefinition $definition, Request $request): void
    {
        if (CustomFieldValue::where('definition_id', $definition->id)->exists()) {
            throw ValidationException::withMessages([
                'id' => ['لا يمكن حذف حقل يحتوي قيماً مسجّلة. عطّله بدلاً من حذفه.'],
            ]);
        }

        $id = $definition->id;
        $meta = ['entity' => $definition->entity, 'key' => $definition->key];
        $definition->delete();

        $this->recordAudit($request, 'custom_field_deleted', CustomFieldDefinition::class, $id, $meta);
    }
}
