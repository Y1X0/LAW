<?php

namespace Modules\Attendance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Attendance\Models\BiometricDevice;
use Modules\Attendance\Services\BiometricSyncService;

/**
 * استقبال بصمات Push/Webhook من الأجهزة (Issue #16, docs/04 §7).
 *
 * لا يخضع لمصادقة المستخدم (RBAC) — الجهاز ليس مستخدماً. التحقق عبر المفتاح
 * السري للجهاز (ترويسة X-Device-Secret) بمقارنة ثابتة الزمن. يُفضّل تقييد المصدر
 * بشبكة المكتب (IP Allowlist/VPN) على مستوى البنية التحتية.
 */
class BiometricWebhookController
{
    public function __construct(private readonly BiometricSyncService $sync) {}

    public function __invoke(Request $request, BiometricDevice $device): JsonResponse
    {
        $provided = (string) $request->header('X-Device-Secret', '');
        abort_unless($device->is_active, 403, 'الجهاز غير مفعّل.');
        abort_unless($provided !== '' && hash_equals($device->secret, $provided), 401, 'مفتاح الجهاز غير صحيح.');

        $result = $this->sync->handleWebhook($device, $request->all(), $request);

        return response()->json(['data' => $result, 'meta' => null, 'errors' => null], 202);
    }
}
