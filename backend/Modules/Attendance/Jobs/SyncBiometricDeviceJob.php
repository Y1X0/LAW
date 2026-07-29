<?php

namespace Modules\Attendance\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Attendance\Biometric\BiometricAdapterFactory;
use Modules\Attendance\Concerns\RecordsSystemAudit;
use Modules\Attendance\Models\BiometricDevice;
use Modules\Attendance\Services\BiometricIngestionService;

/**
 * مزامنة جهاز واحد بنمط Pull/التسوية (Issue #16, docs/04 §3-B).
 * يسحب السجلات منذ last_sync_at، يستوعبها (منع التكرار)، ويحدّث last_sync_at + status.
 * إعادة محاولة أُسّية عبر الطابور تجعل المزامنة شبكة أمان لا تفقد أي سجل.
 */
class SyncBiometricDeviceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RecordsSystemAudit, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $deviceId) {}

    /** @return array<int,int> */
    public function backoff(): array
    {
        return [30, 60, 120];
    }

    public function handle(BiometricAdapterFactory $factory, BiometricIngestionService $ingestion): void
    {
        $device = BiometricDevice::find($this->deviceId);

        if ($device === null || ! $device->is_active) {
            return;
        }

        $adapter = $factory->make($device);
        $punches = $adapter->fetchLogs($device->last_sync_at);

        $ingested = 0;
        foreach ($punches as $punch) {
            if ($ingestion->ingest($device, $punch, 'pull') !== null) {
                $ingested++;
            }
        }

        $device->update([
            'status' => $adapter->getDeviceStatus(),
            'last_sync_at' => now(),
        ]);

        $this->systemAudit('biometric_device_synced', BiometricDevice::class, $device->id, [
            'ingested' => $ingested,
            'status' => $device->status,
        ]);
    }
}
