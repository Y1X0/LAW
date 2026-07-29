<?php

namespace Modules\Attendance\Adapters;

use Illuminate\Support\Carbon;
use Modules\Attendance\Models\BiometricDevice;

/**
 * محوّل ZKTeco (ADR-003) — Issue #16.
 *
 * يفسّر حمولة Push (بروتوكول iClock/ADMS: سجلات ATTLOG مفصولة بالمسافات، أو JSON
 * بمفتاح `records`) ويطبّعها إلى الشكل الموحّد. حالة البصمة (status): 0 = دخول، 1 = خروج.
 *
 * ملاحظة: طبقة النقل للـ Pull (SDK/بروتوكول 4370) تعتمد على شبكة الجهاز ولا تعمل في
 * بيئة الاختبار؛ لذا يُعيد fetchLogs() مصفوفة فارغة في الأساس (Foundation)، بينما تبقى
 * منطقية التطبيع (normalize) ومسار Push مغطّاة ومختبَرة بالكامل.
 */
class ZktecoAdapter implements BiometricAdapter
{
    /** خريطة رمز الحالة في ZKTeco إلى نوع النبضة. */
    private const PUNCH_MAP = [0 => 'check_in', 1 => 'check_out'];

    public function vendor(): string
    {
        return 'zkteco';
    }

    public function fetchLogs(BiometricDevice $device, ?Carbon $since): array
    {
        // النقل الحقيقي عبر SDK/بروتوكول الجهاز يُوصَّل هنا لكل بيئة نشر؛
        // الأساس لا يفترض اتصالاً بجهاز فعلي.
        return [];
    }

    public function parseWebhook(array $payload): array
    {
        // JSON منظّم: {"records": [...]}
        if (isset($payload['records']) && is_array($payload['records'])) {
            return array_values($payload['records']);
        }

        // نص ATTLOG خام: كل سطر "pin<TAB/مسافات>time<...>status<...>verify".
        if (isset($payload['attlog']) && is_string($payload['attlog'])) {
            return $this->parseAttlog($payload['attlog']);
        }

        return [];
    }

    public function normalize(array $raw): array
    {
        $status = isset($raw['status']) ? (int) $raw['status'] : -1;

        return [
            'biometric_user_id' => (string) ($raw['pin'] ?? $raw['user_id'] ?? ''),
            'punch_time' => Carbon::parse($raw['time'] ?? $raw['timestamp']),
            'punch_type' => self::PUNCH_MAP[$status] ?? 'unknown',
            'verify_mode' => $raw['verify'] ?? null,
            'raw_payload' => $raw,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseAttlog(string $attlog): array
    {
        // تنسيق ATTLOG: "PIN  YYYY-MM-DD HH:MM:SS  status  verify" — التاريخ والوقت رمزان منفصلان.
        $records = [];
        foreach (preg_split('/\r\n|\r|\n/', trim($attlog)) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $cols = preg_split('/\s+/', trim($line));
            if (count($cols) < 3) {
                continue;
            }
            $records[] = [
                'pin' => $cols[0],
                'time' => $cols[1].' '.$cols[2],
                'status' => $cols[3] ?? 0,
                'verify' => $cols[4] ?? null,
            ];
        }

        return $records;
    }
}
