<?php

namespace Modules\Core\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Core\Concerns\RecordsAudit;
use Modules\Core\Models\Setting;

/**
 * إعدادات المنصّة العامّة (ADMIN-5) — نقاط جديدة محميّة بصلاحية settings.manage القائمة.
 * تعمل على الإعدادات العامّة (branch_id = null) فقط، وتعيد استخدام جدول settings الموجود.
 * لا Migration، لا تغيير لأي مسار قائم.
 */
class SettingsController
{
    use RecordsAudit;

    /**
     * قائمة السماح للمفاتيح القابلة للكتابة (group => keys). تمنع إنشاء مفاتيح
     * عشوائية أو الكتابة فوق مفاتيح حسّاسة تقرأها وحدات أخرى (تصلّب أمني — Stabilization).
     */
    private const ALLOWED = [
        'general' => ['org_name_ar', 'org_name_en', 'contact_email', 'contact_phone'],
    ];

    /** GET /api/admin/settings — الإعدادات العامّة مجمّعة حسب المجموعة. */
    public function index(): JsonResponse
    {
        $grouped = Setting::query()
            ->whereNull('branch_id')
            ->orderBy('group')->orderBy('key')
            ->get(['id', 'group', 'key', 'value'])
            ->groupBy('group')
            ->map(fn ($items) => $items->map(fn (Setting $s) => [
                'id' => $s->id,
                'key' => $s->key,
                'value' => $s->value,
            ])->values());

        return response()->json(['data' => $grouped, 'meta' => null, 'errors' => null]);
    }

    /** PUT /api/admin/settings — تحديث/إنشاء دفعة إعدادات عامّة (idempotent). */
    public function update(Request $request): JsonResponse
    {
        $allowedKeys = array_merge(...array_values(self::ALLOWED));

        $data = $request->validate([
            'settings' => ['present', 'array', 'max:50'],
            'settings.*.group' => ['required', 'string', Rule::in(array_keys(self::ALLOWED))],
            'settings.*.key' => ['required', 'string', Rule::in($allowedKeys)],
            'settings.*.value' => ['nullable', 'string', 'max:2000'], // نصّ محدود فقط (يمنع type-confusion والحمولات الكبيرة)
        ]);

        // رفض تركيب group/key غير مسموح (مفتاح صحيح لكن في مجموعة خاطئة).
        foreach ($data['settings'] as $item) {
            abort_unless(
                in_array($item['key'], self::ALLOWED[$item['group']] ?? [], true),
                422,
                "المفتاح {$item['key']} غير مسموح ضمن المجموعة {$item['group']}."
            );
        }

        // لقطة القيم القديمة قبل الكتابة (للتدقيق — before-image).
        $before = [];
        foreach ($data['settings'] as $item) {
            $existing = Setting::whereNull('branch_id')->where('group', $item['group'])->where('key', $item['key'])->first();
            $before["{$item['group']}.{$item['key']}"] = $existing?->value;

            Setting::updateOrCreate(
                ['branch_id' => null, 'group' => $item['group'], 'key' => $item['key']],
                ['value' => $item['value']],
            );
        }

        $after = collect($data['settings'])->mapWithKeys(fn ($i) => ["{$i['group']}.{$i['key']}" => $i['value']])->all();

        $this->recordAudit($request, 'settings_updated', Setting::class, 0, $after, $before);

        return $this->index();
    }
}
