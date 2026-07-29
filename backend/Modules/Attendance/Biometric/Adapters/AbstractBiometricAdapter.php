<?php

namespace Modules\Attendance\Biometric\Adapters;

use Illuminate\Support\Carbon;
use Modules\Attendance\Biometric\Contracts\BiometricAdapter;
use Modules\Attendance\Biometric\PunchData;
use Modules\Attendance\Models\BiometricDevice;

/**
 * أساس مشترك للمحوّلات: يحمل الجهاز ويوفّر منطقاً افتراضياً قابلاً لإعادة الاستخدام
 * (فحص الاتصال بمقبس TCP، parseWebhook على مصفوفة records، تعيين الحالة).
 */
abstract class AbstractBiometricAdapter implements BiometricAdapter
{
    public function __construct(protected readonly BiometricDevice $device) {}

    /** فحص وصول عبر مقبس TCP (لا يفشل الطلب عند تعذّر الاتصال). */
    public function connect(): bool
    {
        if (empty($this->device->ip_address) || empty($this->device->port)) {
            return false;
        }

        $socket = @fsockopen($this->device->ip_address, (int) $this->device->port, $errno, $errstr, 2);
        if ($socket === false) {
            return false;
        }
        fclose($socket);

        return true;
    }

    /**
     * تفسير حمولة Webhook قياسية على شكل {"records": [ ...سجلات خام... ]}.
     * المحوّل الخاص بالمورّد يعيد التعريف لدعم صيغ أخرى.
     *
     * @param  array<string,mixed>  $payload
     * @return array<int,PunchData>
     */
    public function parseWebhook(array $payload): array
    {
        $records = $payload['records'] ?? (array_is_list($payload) ? $payload : [$payload]);

        $punches = [];
        foreach ($records as $raw) {
            if (is_array($raw) && $raw !== []) {
                $punches[] = $this->normalize($raw);
            }
        }

        return $punches;
    }

    public function getDeviceStatus(): string
    {
        return $this->connect() ? 'online' : 'offline';
    }

    /** مساعد: تحليل توقيت النبضة إلى Carbon (يفترض توقيت الجهاز/التطبيق). */
    protected function parseTime(mixed $value): Carbon
    {
        return $value instanceof Carbon ? $value : Carbon::parse((string) $value);
    }
}
