<?php

namespace Modules\Attendance\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Attendance\Concerns\RecordsSystemAudit;
use Modules\Attendance\Models\AttendanceLog;
use Modules\Attendance\Services\AttendanceService;

/**
 * معالجة سجل بصمة مطابَق إلى attendance_records (Issue #16, docs/04 §5).
 * يتضمّن إعادة محاولة أُسّية عبر الطابور. لا يعالج سجلاً غير مطابَق أو مُعالَجاً مسبقاً.
 */
class ProcessAttendanceLogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RecordsSystemAudit, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $logId) {}

    /** @return array<int,int> */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(AttendanceService $attendance): void
    {
        $log = AttendanceLog::find($this->logId);

        if ($log === null || $log->status === 'processed' || $log->employee_id === null) {
            return;
        }

        $employee = $log->employee;
        if ($employee === null) {
            return;
        }

        $attendance->applyPunch($employee, $log->punch_time, $log->punch_type, 'biometric');

        $log->update(['status' => 'processed', 'processed_at' => now()]);

        $this->systemAudit('biometric_log_processed', AttendanceLog::class, $log->id, [
            'employee_id' => $employee->id,
            'punch_type' => $log->punch_type,
            'punch_time' => $log->punch_time->toIso8601String(),
        ]);
    }
}
