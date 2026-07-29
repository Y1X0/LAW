<?php

namespace Modules\Attendance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Attendance\Biometric\BiometricAdapterFactory;
use Modules\Attendance\Models\BiometricDevice;
use Modules\Attendance\Services\BiometricIngestionService;

/**
 * مستقبِل Push/Webhook لأجهزة البصمة (Issue #16, docs/04 §3-A).
 * خارج مصادقة المستخدم — يُصادَق بتوكن الجهاز السري (Bearer / X-Device-Token).
 * يفسّر الحمولة عبر محوّل المورّد ثم يستوعبها في Staging (مع منع التكرار).
 */
class BiometricWebhookController
{
    public function __construct(
        private readonly BiometricAdapterFactory $factory,
        private readonly BiometricIngestionService $ingestion,
    ) {}

    /** POST /api/biometric/devices/{device}/webhook */
    public function receive(Request $request, BiometricDevice $device): JsonResponse
    {
        $token = $request->bearerToken() ?? $request->header('X-Device-Token');

        abort_if(
            $device->auth_token === null || ! is_string($token) || ! hash_equals($device->auth_token, $token),
            401,
            'توكن جهاز غير صالح.'
        );
        abort_unless($device->is_active, 403, 'الجهاز غير مفعّل.');

        $adapter = $this->factory->make($device);
        $punches = $adapter->parseWebhook($request->all());

        $received = 0;
        foreach ($punches as $punch) {
            if ($this->ingestion->ingest($device, $punch, 'push') !== null) {
                $received++;
            }
        }

        $device->update(['status' => 'online', 'last_seen_at' => now()]);

        return response()->json([
            'data' => ['received' => $received],
            'meta' => null,
            'errors' => null,
        ]);
    }
}
