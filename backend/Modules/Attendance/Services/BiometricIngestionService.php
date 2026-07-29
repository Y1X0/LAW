<?php

namespace Modules\Attendance\Services;

use Illuminate\Database\QueryException;
use Modules\Attendance\Biometric\PunchData;
use Modules\Attendance\Concerns\RecordsSystemAudit;
use Modules\Attendance\Jobs\ProcessAttendanceLogJob;
use Modules\Attendance\Models\AttendanceLog;
use Modules\Attendance\Models\BiometricDevice;
use Modules\HR\Models\Employee;

/**
 * استيعاب نبضات البصمة (Issue #16, docs/04 §5): منع التكرار (Idempotency)،
 * حفظ خام في Staging، مطابقة الموظف بـ biometric_user_id، ثم إطلاق مهمة المعالجة.
 * السجل غير المطابَق يبقى معلّقاً (unmatched) مع أثر تدقيق يخدم كتنبيه HR للمطابقة.
 */
class BiometricIngestionService
{
    use RecordsSystemAudit;

    /**
     * @param  string  $source  push|pull
     * @return AttendanceLog|null السجل المُنشأ أو الموجود (عند التكرار).
     */
    public function ingest(BiometricDevice $device, PunchData $punch, string $source): ?AttendanceLog
    {
        if ($punch->biometricUserId === '') {
            return null;
        }

        // Idempotency: أي إعادة إرسال لنفس (الجهاز/المستخدم/الوقت) تُتجاهل.
        $existing = $this->findDuplicate($device, $punch);
        if ($existing !== null) {
            return $existing;
        }

        $employee = Employee::where('biometric_user_id', $punch->biometricUserId)->first();

        try {
            $log = AttendanceLog::create([
                'device_id' => $device->id,
                'branch_id' => $device->branch_id,
                'biometric_user_id' => $punch->biometricUserId,
                'employee_id' => $employee?->id,
                'punch_time' => $punch->punchTime,
                'punch_type' => $punch->punchType,
                'verify_mode' => $punch->verifyMode,
                'source' => $source,
                'status' => $employee !== null ? 'matched' : 'unmatched',
                'raw_payload' => $punch->raw,
            ]);
        } catch (QueryException $e) {
            // سباق على الفهرس الفريد → عامله كتكرار.
            return $this->findDuplicate($device, $punch);
        }

        if ($employee !== null) {
            ProcessAttendanceLogJob::dispatch($log->id);
        } else {
            // تعليق + تنبيه HR: السجل يبقى unmatched في قائمة عمل المطابقة.
            $this->systemAudit('biometric_log_unmatched', AttendanceLog::class, $log->id, [
                'device_id' => $device->id,
                'biometric_user_id' => $punch->biometricUserId,
                'punch_time' => $punch->punchTime->toIso8601String(),
            ]);
        }

        return $log;
    }

    private function findDuplicate(BiometricDevice $device, PunchData $punch): ?AttendanceLog
    {
        return AttendanceLog::where('device_id', $device->id)
            ->where('biometric_user_id', $punch->biometricUserId)
            ->where('punch_time', $punch->punchTime)
            ->first();
    }
}
