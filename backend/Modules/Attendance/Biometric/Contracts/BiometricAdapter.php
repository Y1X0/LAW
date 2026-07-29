<?php

namespace Modules\Attendance\Biometric\Contracts;

use Illuminate\Support\Carbon;
use Modules\Attendance\Biometric\PunchData;
use Modules\HR\Models\Employee;

/**
 * الواجهة الموحّدة لأجهزة البصمة (ADR-003, docs/04 §2).
 * يطبّقها محوّل مستقل لكل مورّد، فيبقى جوهر النظام محايداً تجاه نوع الجهاز.
 */
interface BiometricAdapter
{
    /** فتح اتصال بالجهاز (IP/Port). يعيد true عند النجاح. */
    public function connect(): bool;

    /**
     * سحب السجلات الجديدة منذ وقت معيّن (نمط Pull/التسوية).
     *
     * @return array<int,PunchData>
     */
    public function fetchLogs(?Carbon $since): array;

    /**
     * تفسير حمولة Push/Webhook إلى نبضات موحّدة.
     *
     * @param  array<string,mixed>  $payload
     * @return array<int,PunchData>
     */
    public function parseWebhook(array $payload): array;

    /**
     * تحويل سجل خام واحد إلى نموذج موحّد.
     *
     * @param  array<string,mixed>  $raw
     */
    public function normalize(array $raw): PunchData;

    /** دفع مستخدم (biometric_user_id) إلى الجهاز. */
    public function enrollUser(Employee $employee): bool;

    /** إزالة مستخدم من الجهاز. */
    public function deleteUser(string $biometricUserId): bool;

    /** حالة الجهاز: online|offline. */
    public function getDeviceStatus(): string;
}
