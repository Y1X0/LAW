<?php

namespace Modules\Core\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        $data = $request->validate([
            'settings' => ['present', 'array'],
            'settings.*.group' => ['required', 'string', 'max:40'],
            'settings.*.key' => ['required', 'string', 'max:80'],
            'settings.*.value' => ['present'],
        ]);

        foreach ($data['settings'] as $item) {
            Setting::updateOrCreate(
                ['branch_id' => null, 'group' => $item['group'], 'key' => $item['key']],
                ['value' => $item['value']],
            );
        }

        $this->recordAudit($request, 'settings_updated', Setting::class, 0, [
            'keys' => collect($data['settings'])->map(fn ($i) => "{$i['group']}.{$i['key']}")->all(),
        ]);

        return $this->index();
    }
}
