<?php

namespace Modules\Attendance\Adapters;

use Illuminate\Support\Carbon;
use Modules\Attendance\Models\BiometricDevice;

/**
 * الواجهة الموحّدة لمحوّلات أجهزة البصمة (ADR-003, docs/04 §2) — Issue #16.
 *
 * كل مورّد (ZKTeco/Hikvision/Suprema/Anviz) يطبّق هذه الواجهة، فيبقى جوهر
 * النظام محايداً تجاه نوع الجهاز. تُطبَّع كل السجلات إلى شكل موحّد قبل الحفظ.
 */
interface BiometricAdapter
{
    /** اسم المورّد الذي يخدمه هذا المحوّل (zkteco/hikvision/…). */
    public function vendor(): string;

    /** فحص الاتصال بالجهاز (reachability) — يُستخدم قبل السحب/التسجيل. */
    public function connect(BiometricDevice $device): bool;

    /**
     * مزامنة تسجيل موظف إلى الجهاز (Enrollment). طبقة النقل الفعلية تُوصَّل لكل مورّد؛
     * الأساس يعرّف التوقيع فقط ضمن نمط Adapter (ADR-003).
     *
     * @param  array<string, mixed>  $user  بيانات المستخدم (biometric_user_id, name, …)
     */
    public function enrollUser(BiometricDevice $device, array $user): bool;

    /**
     * Pull: سحب السجلات الجديدة من الجهاز منذ آخر مزامنة.
     * يُعيد مصفوفة سجلات خام (كما يفهمها المحوّل) تُمرَّر لاحقاً إلى normalize().
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchLogs(BiometricDevice $device, ?Carbon $since): array;

    /**
     * Push: تفسير حمولة Webhook/Push إلى مصفوفة سجلات خام.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    public function parseWebhook(array $payload): array;

    /**
     * تطبيع سجل خام واحد إلى الشكل الموحّد:
     * ['biometric_user_id' => string, 'punch_time' => Carbon,
     *  'punch_type' => 'check_in'|'check_out'|'unknown', 'verify_mode' => ?string,
     *  'raw_payload' => array].
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    public function normalize(array $raw): array;
}
