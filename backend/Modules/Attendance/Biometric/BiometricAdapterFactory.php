<?php

namespace Modules\Attendance\Biometric;

use InvalidArgumentException;
use Modules\Attendance\Biometric\Adapters\ZKTecoAdapter;
use Modules\Attendance\Biometric\Contracts\BiometricAdapter;
use Modules\Attendance\Models\BiometricDevice;

/**
 * مصنع المحوّلات (Issue #16): يختار المحوّل المناسب حسب مورّد الجهاز.
 * إضافة مورّد جديد = فرع جديد هنا + محوّل جديد فقط (ADR-003).
 * MVP يدعم ZKTeco بالكامل؛ بقية المورّدين محجوزون في مصفوفة الدعم (docs/04 §4).
 */
class BiometricAdapterFactory
{
    public function make(BiometricDevice $device): BiometricAdapter
    {
        return match ($device->vendor) {
            'zkteco' => new ZKTecoAdapter($device),
            default => throw new InvalidArgumentException("مورّد أجهزة غير مدعوم بعد: {$device->vendor}"),
        };
    }
}
