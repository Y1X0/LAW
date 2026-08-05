<?php

namespace Modules\Attendance\Console\Commands;

use Illuminate\Console\Command;
use Modules\Attendance\Jobs\SyncBiometricDeviceJob;
use Modules\Attendance\Models\BiometricDevice;

/**
 * أمر مزامنة Pull/التسوية (Issue #16): يطلق مهمة سحب لكل جهاز نشط بوضع Pull.
 *
 * **يقتصر على `api_mode = pull`**: أجهزة وضع Push تدفع سجلّاتها إلى الـ API فلا حاجة لسحبها
 * (سحبها عبثٌ ومحاولة اتصال بلا داعٍ). الأمر شبكة أمان تلتقط ما فات الـ Push على أجهزة Pull.
 * يتّصل الأمر بأجهزة على شبكة المكتب (LAN)، لذا يبقى **معطّلاً على المضيف السحابي** ولا يُجدوَل
 * إلا حين يوجد مُشغّل داخل شبكة الشركة (راجع بوّابة الجدولة في routes/console.php).
 */
class SyncBiometricDevicesCommand extends Command
{
    protected $signature = 'biometric:sync';

    protected $description = 'مزامنة أجهزة البصمة النشطة بوضع Pull (التسوية) عبر إطلاق مهمة لكل جهاز';

    public function handle(): int
    {
        $count = 0;

        BiometricDevice::where('is_active', true)
            ->where('api_mode', 'pull')
            ->each(function (BiometricDevice $device) use (&$count): void {
                SyncBiometricDeviceJob::dispatch($device->id);
                $count++;
            });

        $this->info("تم جدولة مزامنة {$count} جهاز.");

        return self::SUCCESS;
    }
}
