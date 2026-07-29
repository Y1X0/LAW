<?php

namespace Modules\Attendance\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Attendance\Models\BiometricDevice;
use Modules\Attendance\Services\BiometricSyncService;

/**
 * مهمة سحب (Pull) دورية لجهاز بصمة (Issue #16) — بنية المزامنة عبر الطابور.
 * إعادة المحاولة الأُسّية تضمن التسوية حتى مع انقطاعات الشبكة المؤقتة.
 */
class PullBiometricDeviceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** عدد المحاولات قبل الفشل النهائي. */
    public int $tries = 3;

    public function __construct(public readonly BiometricDevice $device) {}

    /** تأخير إعادة المحاولة (ثوانٍ) — تراجع أُسّي. */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(BiometricSyncService $sync): void
    {
        // لا سياق HTTP في الطابور — طلب اصطناعي للتدقيق (بلا مستخدم).
        $sync->pull($this->device, Request::create('/', 'GET'));
    }
}
