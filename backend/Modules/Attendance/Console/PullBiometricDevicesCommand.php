<?php

namespace Modules\Attendance\Console;

use Illuminate\Console\Command;
use Modules\Attendance\Jobs\PullBiometricDeviceJob;
use Modules\Attendance\Models\BiometricDevice;

/**
 * مزامنة دورية (Pull/Reconciliation) لأجهزة البصمة (Issue #16).
 * تُجدوَل عبر routes/console.php وتُرسل مهمة سحب لكل جهاز نشط بنمط pull.
 */
class PullBiometricDevicesCommand extends Command
{
    protected $signature = 'attendance:sync-biometric';

    protected $description = 'سحب سجلات الحضور من أجهزة البصمة النشطة (نمط pull) عبر الطابور';

    public function handle(): int
    {
        $devices = BiometricDevice::where('is_active', true)->where('api_mode', 'pull')->get();

        foreach ($devices as $device) {
            PullBiometricDeviceJob::dispatch($device);
        }

        $this->info("تم إرسال مهام سحب لـ {$devices->count()} جهاز.");

        return self::SUCCESS;
    }
}
