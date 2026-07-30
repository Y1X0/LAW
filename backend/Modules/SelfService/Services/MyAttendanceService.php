<?php

namespace Modules\SelfService\Services;

use Illuminate\Database\Eloquent\Builder;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\HR\Models\Employee;

/**
 * سجلّ حضور الموظف الذاتي (Epic 9 / Issue #50).
 *
 * **قراءة فقط** من `attendance_records` للموظف الحالي — لا تعديل/كتابة إطلاقاً.
 * كل استعلام مقيّد بـ `employee_id`، فلا يرى الموظف حضور موظف آخر.
 */
class MyAttendanceService
{
    /** سجلّ حضوري ضمن نطاق تاريخي اختياري (الأحدث أولاً). */
    public function listFor(Employee $employee, ?string $from, ?string $to): array
    {
        return AttendanceRecord::where('employee_id', $employee->id)
            ->when($from, fn (Builder $q, $f) => $q->whereDate('work_date', '>=', $f))
            ->when($to, fn (Builder $q, $t) => $q->whereDate('work_date', '<=', $t))
            ->orderByDesc('work_date')
            ->get()
            ->map(fn (AttendanceRecord $r) => [
                'date' => $r->work_date->toDateString(),
                'status' => $r->status,
                'check_in' => $r->check_in?->toIso8601String(),
                'check_out' => $r->check_out?->toIso8601String(),
                'worked_minutes' => (int) $r->worked_minutes,
                'late_minutes' => (int) $r->late_minutes,
                'early_leave_minutes' => (int) $r->early_leave_minutes,
                'overtime_minutes' => (int) $r->overtime_minutes,
            ])->all();
    }
}
