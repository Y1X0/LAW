<?php

namespace Modules\Attendance\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\Attendance\Adapters\BiometricAdapterManager;
use Modules\Attendance\Models\AttendanceLog;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\Attendance\Models\BiometricDevice;
use Modules\Core\Concerns\RecordsAudit;
use Modules\HR\Models\Employee;

/**
 * مزامنة أجهزة البصمة (Issue #16, ADR-003):
 * استقبال البصمات (Push/Pull) إلى طبقة Staging مع منع التكرار (Idempotency)،
 * ثم معالجتها إلى attendance_records عبر AttendanceService (source=biometric).
 */
class BiometricSyncService
{
    use RecordsAudit;

    public function __construct(
        private readonly BiometricAdapterManager $adapters,
        private readonly AttendanceService $attendance,
    ) {}

    /**
     * استقبال حمولة Push/Webhook: تفسير + حفظ خام (Staging) + معالجة.
     *
     * @param  array<string, mixed>  $payload
     * @return array{ingested:int, duplicates:int, processed:int, unmatched:int}
     */
    public function handleWebhook(BiometricDevice $device, array $payload, Request $request): array
    {
        $raw = $this->adapters->for($device)->parseWebhook($payload);
        $stored = $this->ingest($device, $raw, $request);

        $device->forceFill(['last_sync_at' => now(), 'status' => 'online'])->save();
        $this->recordAudit($request, 'biometric_webhook_received', BiometricDevice::class, $device->id, [
            'ingested' => $stored['ingested'], 'duplicates' => $stored['duplicates'],
        ]);

        return $stored + $this->processPending($request, $device);
    }

    /**
     * سحب دوري (Pull) من الجهاز منذ آخر مزامنة + معالجة (شبكة أمان/تسوية).
     *
     * @return array{ingested:int, duplicates:int, processed:int, unmatched:int}
     */
    public function pull(BiometricDevice $device, Request $request): array
    {
        $adapter = $this->adapters->for($device);
        $raw = $adapter->fetchLogs($device, $device->last_sync_at ? Carbon::parse($device->last_sync_at) : null);
        $stored = $this->ingest($device, $raw, $request);

        $device->forceFill(['last_sync_at' => now(), 'status' => 'online'])->save();
        $this->recordAudit($request, 'biometric_pulled', BiometricDevice::class, $device->id, [
            'ingested' => $stored['ingested'], 'duplicates' => $stored['duplicates'],
        ]);

        return $stored + $this->processPending($request, $device);
    }

    /**
     * حفظ السجلات الخام في Staging مع منع التكرار عبر الفهرس الفريد المنطقي.
     *
     * @param  array<int, array<string, mixed>>  $rawRecords
     * @return array{ingested:int, duplicates:int}
     */
    public function ingest(BiometricDevice $device, array $rawRecords, Request $request): array
    {
        $adapter = $this->adapters->for($device);
        $ingested = 0;
        $duplicates = 0;

        foreach ($rawRecords as $raw) {
            $norm = $adapter->normalize($raw);
            if ($norm['biometric_user_id'] === '') {
                continue;
            }

            $key = [
                'device_id' => $device->id,
                'biometric_user_id' => $norm['biometric_user_id'],
                'punch_time' => $norm['punch_time'],
            ];

            // Idempotency: إعادة الإرسال تطابق الفهرس الفريد فلا تُنشئ سجلاً جديداً.
            $log = AttendanceLog::firstOrCreate($key, [
                'punch_type' => $norm['punch_type'],
                'verify_mode' => $norm['verify_mode'],
                'raw_payload' => $norm['raw_payload'],
                'status' => 'pending',
            ]);

            $log->wasRecentlyCreated ? $ingested++ : $duplicates++;
        }

        return ['ingested' => $ingested, 'duplicates' => $duplicates];
    }

    /**
     * معالجة سجلات Staging المعلّقة: مطابقة الموظف ثم التطبيق على الحضور.
     *
     * @return array{processed:int, unmatched:int}
     */
    public function processPending(Request $request, ?BiometricDevice $device = null): array
    {
        $query = AttendanceLog::where('status', 'pending')->orderBy('punch_time');
        if ($device) {
            $query->where('device_id', $device->id);
        }

        $processed = 0;
        $unmatched = 0;

        foreach ($query->get() as $log) {
            $employee = Employee::where('biometric_user_id', $log->biometric_user_id)->first();

            if (! $employee) {
                $log->forceFill(['status' => 'unmatched'])->save();
                $unmatched++;

                // تنبيه HR: بصمة بلا موظف مطابق — تُعلَّق ولا تُفقد (docs/04 §5).
                Log::warning('بصمة غير مطابَقة لأي موظف — بحاجة لمراجعة HR', [
                    'device_id' => $log->device_id,
                    'biometric_user_id' => $log->biometric_user_id,
                    'punch_time' => (string) $log->punch_time,
                ]);

                continue;
            }

            $this->applyPunch($employee, $log, $request);
            $log->forceFill([
                'employee_id' => $employee->id,
                'status' => 'processed',
                'processed_at' => now(),
            ])->save();
            $processed++;
        }

        return ['processed' => $processed, 'unmatched' => $unmatched];
    }

    /** تطبيق نبضة واحدة على سجل الحضور (دخول أول نبضة، خروج للاحقة). */
    private function applyPunch(Employee $employee, AttendanceLog $log, Request $request): void
    {
        $time = Carbon::parse($log->punch_time);
        $workDate = $time->toDateString();

        $record = AttendanceRecord::where('employee_id', $employee->id)
            ->whereDate('work_date', $workDate)
            ->first();
        $hasCheckIn = $record && $record->check_in !== null;

        $wantCheckOut = $log->punch_type === 'check_out'
            || ($log->punch_type === 'unknown' && $hasCheckIn);

        if ($wantCheckOut && $hasCheckIn) {
            $this->attendance->checkOut($employee, $time, $request);
        } else {
            $this->attendance->checkIn($employee, $time, $request, 'biometric');
        }
    }
}
