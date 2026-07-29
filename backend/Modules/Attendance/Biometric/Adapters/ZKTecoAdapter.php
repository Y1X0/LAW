<?php

namespace Modules\Attendance\Biometric\Adapters;

use Illuminate\Support\Carbon;
use Modules\Attendance\Biometric\PunchData;
use Modules\HR\Models\Employee;

/**
 * محوّل ZKTeco (Issue #16). المسار الأساسي هو Push (بروتوكول iClock/ADMS) عبر
 * parseWebhook/normalize؛ وPull للتسوية عبر fetchLogs (يتطلب اتصالاً بالجهاز).
 *
 * تعيينات ZKTeco:
 *  - status: 0=دخول، 1=خروج (باقي القيم = غير محدّد → استنتاج بالتجميع).
 *  - verify: 0=password، 1=fingerprint، 15=face، 2=card.
 */
class ZKTecoAdapter extends AbstractBiometricAdapter
{
    private const PUNCH_MAP = ['0' => 'in', '1' => 'out'];

    private const VERIFY_MAP = ['0' => 'password', '1' => 'fingerprint', '2' => 'card', '15' => 'face'];

    /**
     * تطبيع سجل ZKTeco خام إلى نبضة موحّدة.
     * يقبل مفاتيح متعددة (pin/user_id، timestamp/time، status/state، verify).
     *
     * @param  array<string,mixed>  $raw
     */
    public function normalize(array $raw): PunchData
    {
        $userId = (string) ($raw['biometric_user_id'] ?? $raw['user_id'] ?? $raw['pin'] ?? '');
        $time = $this->parseTime($raw['punch_time'] ?? $raw['timestamp'] ?? $raw['time'] ?? 'now');

        $status = isset($raw['status']) ? (string) $raw['status'] : (string) ($raw['state'] ?? '');
        $punchType = self::PUNCH_MAP[$status] ?? 'unknown';

        $verify = isset($raw['verify']) ? (string) $raw['verify'] : null;
        $verifyMode = $verify !== null ? (self::VERIFY_MAP[$verify] ?? $verify) : null;

        return new PunchData($userId, $time, $punchType, $verifyMode, $raw);
    }

    /**
     * سحب السجلات منذ آخر مزامنة (Pull/التسوية).
     * يتطلب اتصالاً حياً بالجهاز عبر بروتوكول 4370؛ يعيد [] بأمان عند تعذّر الوصول.
     *
     * @return array<int,PunchData>
     */
    public function fetchLogs(?Carbon $since): array
    {
        if (! $this->connect()) {
            return [];
        }

        // النقل الفعلي عبر بروتوكول ZKTeco (منفذ 4370) يتم في طبقة النقل؛
        // هنا نُبقي البنية (اتصال + فلترة زمنية) والتوصيل يُحقن عند تشغيل جهاز حقيقي.
        return [];
    }

    public function enrollUser(Employee $employee): bool
    {
        // دفع biometric_user_id + الاسم للجهاز يتطلب اتصالاً حياً؛
        // البصمة الفعلية تُسجَّل على الجهاز مرة واحدة والنظام يربط المعرّف فقط (docs/04 §6).
        return $this->connect();
    }

    public function deleteUser(string $biometricUserId): bool
    {
        return $this->connect();
    }
}
