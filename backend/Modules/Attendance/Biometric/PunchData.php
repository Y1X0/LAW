<?php

namespace Modules\Attendance\Biometric;

use Illuminate\Support\Carbon;

/**
 * نبضة بصمة موحّدة بعد التطبيع (normalize) — الشكل المحايد تجاه المورّد (docs/04 §2).
 * كل محوّل يحوّل السجل الخام لكل مورّد إلى هذا النموذج قبل الاستيعاب.
 */
final class PunchData
{
    /**
     * @param  string  $punchType  in|out|unknown
     * @param  array<string,mixed>  $raw  الحمولة الخام كما وردت من الجهاز (تُحفظ في Staging).
     */
    public function __construct(
        public readonly string $biometricUserId,
        public readonly Carbon $punchTime,
        public readonly string $punchType = 'unknown',
        public readonly ?string $verifyMode = null,
        public readonly array $raw = [],
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'biometric_user_id' => $this->biometricUserId,
            'punch_time' => $this->punchTime->toIso8601String(),
            'punch_type' => $this->punchType,
            'verify_mode' => $this->verifyMode,
        ];
    }
}
