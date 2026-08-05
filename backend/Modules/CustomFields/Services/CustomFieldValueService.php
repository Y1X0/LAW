<?php

namespace Modules\CustomFields\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Core\Concerns\RecordsAudit;
use Modules\CustomFields\Exceptions\CustomFieldForbiddenException;
use Modules\CustomFields\Models\CustomFieldDefinition;
use Modules\CustomFields\Models\CustomFieldValue;

/**
 * محرّك قيم الحقول المخصّصة (Phase 12 · PR-3). القراءة تُصفّى بصلاحية الحقل (view_roles) وسياق
 * العرض (display_in) — بعد أن يكون المتّصل قد اجتاز عزل الكيان (guardCaseView) في المتحكّم.
 * الكتابة تفرض edit_roles (تغيير حقل غير مصرّح ⇒ رفضٌ ذرّي يسمّي الحقل)، وتُدقّق كل تغيّر قيمة.
 * Owner (يملك custom_fields.manage) يتجاوز قيود الحقول — يرى ويعدّل الكل.
 */
class CustomFieldValueService
{
    use RecordsAudit;

    /** @var array<string, Collection<int, CustomFieldDefinition>> تعريفات مفعّلة مُخزّنة لكل كيان */
    private array $defCache = [];

    /** @var array<int, string[]> أسماء أدوار المستخدم مُخزّنة لكل معرّف */
    private array $roleCache = [];

    /**
     * يقرأ قيم الحقول المرئية لصفوف كيان دفعةً (بلا N+1)، مصفّاة بصلاحية view_roles وسياق العرض.
     * يعيد map: entity_id => [ ['key','label','type','value'], ... ] بترتيب sort_order (قيمة null للفارغ).
     *
     * @param  int[]  $entityIds
     * @return array<int, array<int, array{key:string,label:string,type:string,value:mixed}>>
     */
    public function read(string $entity, array $entityIds, ?User $user, string $context): array
    {
        $entityIds = array_values(array_unique(array_filter($entityIds)));
        if ($entityIds === []) {
            return [];
        }

        $definitions = $this->activeDefinitions($entity)
            ->filter(fn (CustomFieldDefinition $d) => $this->contextIncludes($d, $context) && $this->canView($d, $user))
            ->values();
        if ($definitions->isEmpty()) {
            return array_fill_keys($entityIds, []);
        }

        $values = CustomFieldValue::where('entity', $entity)
            ->whereIn('entity_id', $entityIds)
            ->whereIn('definition_id', $definitions->pluck('id'))
            ->get();

        $byEntityDef = [];
        foreach ($values as $v) {
            $byEntityDef[$v->entity_id][$v->definition_id] = $v;
        }

        $out = [];
        foreach ($entityIds as $id) {
            $out[$id] = $definitions->map(function (CustomFieldDefinition $def) use ($byEntityDef, $id) {
                $v = $byEntityDef[$id][$def->id] ?? null;

                return [
                    'key' => $def->key,
                    'label' => $def->label,
                    'type' => $def->type,
                    'value' => $v !== null ? $this->readValue($def, $v) : null,
                ];
            })->all();
        }

        return $out;
    }

    /**
     * يكتب قيم الحقول المخصّصة لصفّ كيان (داخل معاملة المُستدعي — حفظ ذرّي). يفرض edit_roles
     * على كل حقل متغيّر، ويُدقّق كل تغيّر (old→new). $auditableType هو صنف الكيان (سلسلة) لربط
     * التدقيق به دون أن تعتمد هذه الوحدة على وحدة الكيان.
     *
     * @param  array<string, mixed>  $submitted  مفتاح الحقل ⇒ القيمة الخام
     *
     * @throws CustomFieldForbiddenException|ValidationException
     */
    public function write(array $submitted, string $entity, int $entityId, string $auditableType, Request $request, string $context): void
    {
        $definitions = $this->activeDefinitions($entity);
        if ($definitions->isEmpty()) {
            // لا حقول مُعرّفة: أي مفاتيح مُقدَّمة تُعتبر غير معروفة.
            $this->assertNoUnknownKeys($submitted, collect());

            return;
        }

        $byKey = $definitions->keyBy('key');
        $user = $request->user();
        $errors = [];

        $this->collectUnknownKeys($submitted, $byKey, $errors);

        $current = CustomFieldValue::where('entity', $entity)->where('entity_id', $entityId)
            ->whereIn('definition_id', $definitions->pluck('id'))->get()->keyBy('definition_id');

        $forbidden = [];
        $writes = [];
        foreach ($submitted as $key => $raw) {
            $def = $byKey->get($key);
            if ($def === null) {
                continue; // مفتاح غير معروف — سُجِّل خطؤه
            }
            $coerced = $this->coerce($def, $raw, $errors);
            if ($coerced === null) {
                continue; // قيمة غير صالحة — سُجِّل خطؤها
            }

            $existing = $current->get($def->id);
            $old = $existing !== null ? $this->readValue($def, $existing) : null;
            $new = $coerced['display'];

            if ($this->normalize($old) === $this->normalize($new)) {
                continue; // بلا تغيير — لا صلاحية ولا تدقيق
            }
            if (! $this->canEdit($def, $user)) {
                $forbidden[] = $key;

                continue;
            }
            $writes[] = ['def' => $def, 'columns' => $coerced['columns'], 'old' => $old, 'new' => $new, 'existing' => $existing];
        }

        // الحقول الإلزامية — تُفرَض فقط في سياق يدعمها (create/edit) كي لا تكسر الاستيراد/المسارات القديمة.
        foreach ($definitions as $def) {
            if ($def->required && $this->contextIncludes($def, $context) && $this->finalValueEmpty($def, $submitted, $current)) {
                $errors["custom_fields.{$def->key}"] = ["الحقل «{$def->label}» إلزامي."];
            }
        }

        if ($forbidden !== []) {
            throw new CustomFieldForbiddenException(array_values(array_unique($forbidden)));
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        foreach ($writes as $w) {
            CustomFieldValue::updateOrCreate(
                ['definition_id' => $w['def']->id, 'entity' => $entity, 'entity_id' => $entityId],
                $w['columns'] + ['updated_by' => $user?->id] + ($w['existing'] === null ? ['created_by' => $user?->id] : []),
            );
            $this->recordAudit(
                $request, 'custom_field_value_changed', $auditableType, $entityId,
                ['field' => $w['def']->key, 'new' => $w['new']],
                ['field' => $w['def']->key, 'old' => $w['old']],
            );
        }
    }

    /** @return Collection<int, CustomFieldDefinition> */
    private function activeDefinitions(string $entity): Collection
    {
        return $this->defCache[$entity] ??= CustomFieldDefinition::where('entity', $entity)
            ->where('is_active', true)
            ->orderBy('sort_order')->orderBy('id')
            ->get();
    }

    private function contextIncludes(CustomFieldDefinition $def, string $context): bool
    {
        $contexts = $def->display_in;

        return empty($contexts) || in_array($context, $contexts, true);
    }

    private function canView(CustomFieldDefinition $def, ?User $user): bool
    {
        return $this->roleGate($def->view_roles, $user);
    }

    private function canEdit(CustomFieldDefinition $def, ?User $user): bool
    {
        return $this->roleGate($def->edit_roles, $user);
    }

    /** يمرّ إن كانت القائمة فارغة (يرث الكيان)، أو تقاطعت أدوار المستخدم معها، أو كان المستخدم مديراً. */
    private function roleGate(?array $roles, ?User $user): bool
    {
        if ($user === null) {
            return false;
        }
        if ($user->hasPermission('custom_fields.manage')) {
            return true; // Owner/admin يتجاوز قيود الحقول
        }
        if (empty($roles)) {
            return true; // يرث صلاحية الكيان
        }

        return array_intersect($this->userRoleNames($user), $roles) !== [];
    }

    /** @return string[] */
    private function userRoleNames(User $user): array
    {
        return $this->roleCache[$user->id] ??= $user->roles()->pluck('name')->all();
    }

    private function readValue(CustomFieldDefinition $def, CustomFieldValue $v): mixed
    {
        return match ($def->type) {
            'number', 'currency' => $v->value_number !== null ? (float) $v->value_number : null,
            'date' => $v->value_date?->toDateString(),
            'boolean' => $v->value_boolean,
            default => $v->value_text,
        };
    }

    /**
     * يحوّل قيمة خام إلى أعمدة القيمة حسب النوع مع التحقّق. يعيد null ويملأ $errors عند الفشل.
     * القيمة الفارغة تُفرَّغ كل الأعمدة (مسح القيمة).
     *
     * @return array{columns:array<string,mixed>,display:mixed}|null
     */
    private function coerce(CustomFieldDefinition $def, mixed $raw, array &$errors): ?array
    {
        $key = "custom_fields.{$def->key}";
        $empty = ['value_text' => null, 'value_number' => null, 'value_date' => null, 'value_boolean' => null];

        if ($raw === null || $raw === '' || (is_string($raw) && trim($raw) === '')) {
            return ['columns' => $empty, 'display' => null];
        }

        $str = is_string($raw) ? trim($raw) : $raw;

        switch ($def->type) {
            case 'number':
            case 'currency':
                if (! is_numeric($raw)) {
                    $errors[$key] = ['قيمة رقمية غير صحيحة.'];

                    return null;
                }

                return ['columns' => ['value_number' => (float) $raw] + $empty, 'display' => (float) $raw];

            case 'date':
                try {
                    $d = Carbon::parse($raw)->toDateString();
                } catch (\Throwable) {
                    $errors[$key] = ['تاريخ غير صحيح.'];

                    return null;
                }

                return ['columns' => ['value_date' => $d] + $empty, 'display' => $d];

            case 'boolean':
                $b = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($b === null) {
                    $errors[$key] = ['قيمة منطقية غير صحيحة.'];

                    return null;
                }

                return ['columns' => ['value_boolean' => $b] + $empty, 'display' => $b];

            case 'email':
                if (! filter_var($str, FILTER_VALIDATE_EMAIL)) {
                    $errors[$key] = ['بريد إلكتروني غير صحيح.'];

                    return null;
                }

                return ['columns' => ['value_text' => $str] + $empty, 'display' => $str];

            case 'url':
                if (! filter_var($str, FILTER_VALIDATE_URL)) {
                    $errors[$key] = ['رابط غير صحيح.'];

                    return null;
                }

                return ['columns' => ['value_text' => $str] + $empty, 'display' => $str];

            case 'dropdown':
                if (! in_array($str, $def->options ?? [], true)) {
                    $errors[$key] = ['قيمة خارج الخيارات المسموحة.'];

                    return null;
                }

                return ['columns' => ['value_text' => $str] + $empty, 'display' => $str];

            default: // text, longtext, phone
                return ['columns' => ['value_text' => (string) $str] + $empty, 'display' => (string) $str];
        }
    }

    /** @param  Collection<int, CustomFieldDefinition>  $byKey */
    private function collectUnknownKeys(array $submitted, Collection $byKey, array &$errors): void
    {
        foreach (array_keys($submitted) as $key) {
            if (! $byKey->has($key)) {
                $errors["custom_fields.$key"] = ["حقل مخصّص غير معرّف: $key"];
            }
        }
    }

    /** @param  Collection<int, CustomFieldDefinition>  $byKey */
    private function assertNoUnknownKeys(array $submitted, Collection $byKey): void
    {
        $errors = [];
        $this->collectUnknownKeys($submitted, $byKey, $errors);
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @param  Collection<int, CustomFieldValue>  $current */
    private function finalValueEmpty(CustomFieldDefinition $def, array $submitted, Collection $current): bool
    {
        if (array_key_exists($def->key, $submitted)) {
            $raw = $submitted[$def->key];

            return $raw === null || $raw === '' || (is_string($raw) && trim($raw) === '');
        }
        $existing = $current->get($def->id);

        return $existing === null || $this->readValue($def, $existing) === null;
    }

    private function normalize(mixed $v): string
    {
        if ($v === null) {
            return '';
        }
        if (is_bool($v)) {
            return $v ? '1' : '0';
        }

        return (string) $v;
    }
}
