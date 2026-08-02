<?php

namespace Modules\Attendance\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\Attendance\Models\EmployeeShift;
use Modules\Attendance\Models\WorkShift;
use Modules\Core\Concerns\RecordsAudit;
use Modules\HR\Models\Employee;

/**
 * محرّك الحضور (Issue #15): تسجيل الدخول/الخروج، احتساب الساعات والتأخير/الإضافي،
 * التسجيل اليدوي، ودورة الاعتماد. لا تكامل مع أجهزة البصمة (source=manual؛ #16 لاحقاً).
 */
class AttendanceService
{
    use RecordsAudit;

    /** النوبة النشطة للموظف في تاريخ معيّن. */
    public function resolveShift(Employee $employee, string $workDate): ?WorkShift
    {
        return EmployeeShift::where('employee_id', $employee->id)
            ->whereDate('from_date', '<=', $workDate)
            ->where(fn ($q) => $q->whereNull('to_date')->orWhereDate('to_date', '>=', $workDate))
            ->orderByDesc('from_date')
            ->first()?->shift;
    }

    /** تسجيل دخول الموظف (يحتسب التأخير مقابل النوبة). */
    public function checkIn(Employee $employee, Carbon $time, Request $request, string $source = 'manual'): AttendanceRecord
    {
        $workDate = $time->toDateString();
        $shift = $this->resolveShift($employee, $workDate);

        $record = $this->findOrNewDaily($employee, $workDate);
        $record->branch_id = $employee->branch_id;
        $record->source = $source;
        $record->shift_id = $shift?->id;
        $record->created_by ??= $request->user()?->id;

        // لا نطمس أول دخول إن وُجد
        if ($record->check_in === null) {
            $record->check_in = $time;
        }

        $record->late_minutes = $this->computeLate($record->check_in, $shift, $workDate);
        $record->status = $record->late_minutes > 0 ? 'late' : 'present';
        $this->recalcWorked($record);
        $record->save();

        $this->recordAudit($request, 'attendance_check_in', AttendanceRecord::class, $record->id, [
            'employee_id' => $employee->id, 'work_date' => $workDate,
        ]);

        return $record;
    }

    /** تسجيل خروج الموظف (يحتسب ساعات العمل والمغادرة المبكرة والإضافي). */
    public function checkOut(Employee $employee, Carbon $time, Request $request): AttendanceRecord
    {
        $workDate = $time->toDateString();
        $record = AttendanceRecord::where('employee_id', $employee->id)->whereDate('work_date', $workDate)->first();

        abort_if($record === null || $record->check_in === null, 422, 'لا يوجد تسجيل دخول لهذا اليوم.');

        // وقت الخروج لا يسبق الدخول — يماثل تحقّق storeManual (after_or_equal:check_in).
        // بدونه ينتج worked_minutes سالب (diffInMinutes في Carbon 3 موقّع) ويُفسِد تقارير الحضور.
        abort_if($time->lt($record->check_in), 422, 'وقت الخروج لا يمكن أن يسبق وقت الدخول.');

        $shift = $record->shift_id ? WorkShift::find($record->shift_id) : $this->resolveShift($employee, $workDate);
        $record->check_out = $time;
        $this->recalcWorked($record);

        if ($shift) {
            $shiftEnd = Carbon::parse($workDate.' '.$shift->end_time);
            $record->early_leave_minutes = $time->lt($shiftEnd) ? (int) round($time->diffInMinutes($shiftEnd)) : 0;
            $record->overtime_minutes = $time->gt($shiftEnd) ? (int) round($shiftEnd->diffInMinutes($time)) : 0;
        }

        $record->save();

        $this->recordAudit($request, 'attendance_check_out', AttendanceRecord::class, $record->id, [
            'employee_id' => $employee->id, 'worked_minutes' => $record->worked_minutes,
        ]);

        return $record;
    }

    /** تسجيل/تعديل يدوي (يتطلب اعتماداً لاحقاً). */
    public function storeManual(Employee $employee, array $data, Request $request): AttendanceRecord
    {
        $workDate = $data['work_date'];
        $shift = $this->resolveShift($employee, $workDate);

        $record = $this->findOrNewDaily($employee, $workDate);
        $record->fill($data);
        $record->employee_id = $employee->id;
        $record->branch_id = $employee->branch_id;
        $record->source = 'manual';
        $record->shift_id = $shift?->id;
        $record->created_by = $request->user()?->id;
        $record->approved_by = null; // معلّق للاعتماد
        $record->approved_at = null;

        if (! empty($record->check_in)) {
            $record->late_minutes = $this->computeLate(Carbon::parse($record->check_in), $shift, $workDate);
        }
        $this->recalcWorked($record);
        $record->save();

        $this->recordAudit($request, 'attendance_manual_recorded', AttendanceRecord::class, $record->id, [
            'employee_id' => $employee->id, 'work_date' => $workDate, 'status' => $record->status,
        ]);

        return $record;
    }

    /**
     * تطبيق نبضة بصمة على سجل اليوم (Issue #16): أول دخول = check_in، آخر خروج = check_out.
     * تُستدعى من معالج البصمة (بلا طلب HTTP)؛ التدقيق يُسجَّل في المعالج كحدث نظام.
     *
     * @param  string  $punchType  in|out|unknown (unknown يُستنتج بالتجميع)
     */
    public function applyPunch(Employee $employee, Carbon $time, string $punchType, string $source = 'biometric'): AttendanceRecord
    {
        $workDate = $time->toDateString();
        $shift = $this->resolveShift($employee, $workDate);

        $record = $this->findOrNewDaily($employee, $workDate);
        $record->branch_id = $employee->branch_id;
        $record->source = $source;
        $record->shift_id ??= $shift?->id;

        $type = $this->resolvePunchType($record, $punchType);

        if ($type === 'in') {
            if ($record->check_in === null || $time->lt($record->check_in)) {
                $record->check_in = $time; // أول دخول
            }
        } elseif ($record->check_out === null || $time->gt($record->check_out)) {
            $record->check_out = $time; // آخر خروج
        }

        $record->late_minutes = $this->computeLate($record->check_in, $shift, $workDate);
        $this->recalcWorked($record);

        if ($record->check_out && $shift) {
            $shiftEnd = Carbon::parse($workDate.' '.$shift->end_time);
            $out = $record->check_out;
            $record->early_leave_minutes = $out->lt($shiftEnd) ? (int) round($out->diffInMinutes($shiftEnd)) : 0;
            $record->overtime_minutes = $out->gt($shiftEnd) ? (int) round($shiftEnd->diffInMinutes($out)) : 0;
        }

        $record->status = $record->late_minutes > 0 ? 'late' : 'present';
        $record->save();

        return $record;
    }

    /** استنتاج نوع النبضة عند غياب التحديد: لا دخول بعد ⇒ دخول، وإلا خروج. */
    private function resolvePunchType(AttendanceRecord $record, string $punchType): string
    {
        if (in_array($punchType, ['in', 'out'], true)) {
            return $punchType;
        }

        return $record->check_in === null ? 'in' : 'out';
    }

    /** اعتماد سجل حضور (يتطلب صلاحية attendance.approve). */
    public function approve(AttendanceRecord $record, Request $request): AttendanceRecord
    {
        $record->approved_by = $request->user()?->id;
        $record->approved_at = now();
        $record->save();

        $this->recordAudit($request, 'attendance_approved', AttendanceRecord::class, $record->id, [
            'employee_id' => $record->employee_id,
        ]);

        return $record;
    }

    /** يجد سجل اليوم أو يُنشئ نموذجاً جديداً (whereDate يوحّد المقارنة بين SQLite/PostgreSQL). */
    private function findOrNewDaily(Employee $employee, string $workDate): AttendanceRecord
    {
        return AttendanceRecord::where('employee_id', $employee->id)
            ->whereDate('work_date', $workDate)
            ->first()
            ?? new AttendanceRecord(['employee_id' => $employee->id, 'work_date' => $workDate]);
    }

    private function computeLate(?Carbon $checkIn, ?WorkShift $shift, string $workDate): int
    {
        if (! $checkIn || ! $shift) {
            return 0;
        }

        $graceEnd = Carbon::parse($workDate.' '.$shift->start_time)->addMinutes((int) $shift->grace_minutes);

        return $checkIn->gt($graceEnd) ? (int) round($graceEnd->diffInMinutes($checkIn)) : 0;
    }

    private function recalcWorked(AttendanceRecord $record): void
    {
        if ($record->check_in && $record->check_out) {
            $record->worked_minutes = (int) round(Carbon::parse($record->check_in)->diffInMinutes(Carbon::parse($record->check_out)));
        }
    }
}
