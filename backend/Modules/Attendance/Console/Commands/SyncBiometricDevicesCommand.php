<?php

namespace Modules\Attendance\Console\Commands;

use Illuminate\Console\Command;
use Modules\Attendance\Jobs\SyncBiometricDeviceJob;
use Modules\Attendance\Models\BiometricDevice;

/**
 * أمر مجدول (Issue #16): يطلق مهمة مزامنة Pull/التسوية لكل جهاز نشط.
 * يُجدوَل كل دقيقة في routes/console.php ليكون شبكة أمان تلتقط ما فات الـ Push.
 */
class SyncBiometricDevicesCommand extends Command
{
    protected $signature = 'biometric:sync';

    protected $description = 'مزامنة أجهزة البصمة النشطة (Pull/التسوية) عبر إطلاق مهمة لكل جهاز';

    public function handle(): int
    {
        $count = 0;

        BiometricDevice::where('is_active', true)->each(function (BiometricDevice $device) use (&$count): void {
            SyncBiometricDeviceJob::dispatch($device->id);
            $count++;
        });

        $this->info("تم جدولة مزامنة {$count} جهاز.");

        return self::SUCCESS;
    }
}
