<?php

namespace Modules\Attendance\Adapters;

use InvalidArgumentException;
use Modules\Attendance\Models\BiometricDevice;

/**
 * سجلّ محوّلات البصمة (ADR-003) — Issue #16.
 *
 * يحلّ المحوّل المناسب حسب مورّد الجهاز. إضافة مورّد جديد = تسجيل محوّل جديد فقط
 * دون تعديل منطق الحضور. قابل للتوسعة عبر extend() (يُستخدم أيضاً في الاختبارات).
 */
class BiometricAdapterManager
{
    /** @var array<string, callable():BiometricAdapter> */
    private array $factories = [];

    /** @var array<string, BiometricAdapter> */
    private array $resolved = [];

    public function __construct()
    {
        $this->extend('zkteco', fn () => new ZktecoAdapter);
    }

    /** تسجيل/استبدال محوّل لمورّد معيّن. */
    public function extend(string $vendor, callable $factory): void
    {
        $this->factories[$vendor] = $factory;
        unset($this->resolved[$vendor]);
    }

    /** حلّ المحوّل حسب اسم المورّد. */
    public function driver(string $vendor): BiometricAdapter
    {
        if (! isset($this->factories[$vendor])) {
            throw new InvalidArgumentException("لا يوجد محوّل بصمة للمورّد [{$vendor}].");
        }

        return $this->resolved[$vendor] ??= ($this->factories[$vendor])();
    }

    /** المحوّل الخاص بجهاز معيّن. */
    public function for(BiometricDevice $device): BiometricAdapter
    {
        return $this->driver($device->vendor);
    }

    /** هل يوجد محوّل مسجّل لهذا المورّد؟ */
    public function supports(string $vendor): bool
    {
        return isset($this->factories[$vendor]);
    }
}
