<?php

namespace Modules\Leave\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Modules\Core\Concerns\RecordsAudit;
use Modules\HR\Models\Employee;
use Modules\Leave\Events\LeaveApproved;
use Modules\Leave\Events\LeaveCancelled;
use Modules\Leave\Models\LeaveBalance;
use Modules\Leave\Models\LeaveRequest;
use Modules\Leave\Models\LeaveType;

/**
 * منطق الإجازات (Issue #17, docs/03 أ-2, docs/10 §2.2):
 * احتساب أيام العمل، التحقق من التعارض/الرصيد/المرفق، دورة الاعتماد وخصم الرصيد،
 * والتصعيد عندما يكون المقدّم مديراً. تحديث الحضور عبر حدث (يستهلكه الحضور لاحقاً).
 */
class LeaveService
{
    use RecordsAudit;

    /** أيام نهاية الأسبوع (Carbon: 5=الجمعة، 6=السبت). */
    private const WEEKEND = [Carbon::FRIDAY, Carbon::SATURDAY];

    /** تقديم طلب إجازة (يتحقق من كل حالات التعارض). */
    public function request(Employee $employee, array $data, Request $request): LeaveRequest
    {
        $type = LeaveType::where('id', $data['leave_type_id'])->where('is_active', true)->firstOrFail();
        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);

        $this->assert($end->gte($start), 'start_date', 'تاريخ النهاية قبل البداية.');
        $this->assert($start->year === $end->year, 'start_date', 'لا يمكن أن يمتد الطلب عبر سنتين — قسّمه لطلبين.');
        $this->assert(! $type->requires_attachment || ! empty($data['attachment_path']), 'attachment_path', 'هذا النوع يتطلب مرفقاً.');

        $days = $this->workingDays($start, $end);
        $this->assert($days > 0, 'start_date', 'لا توجد أيام عمل ضمن الفترة (كلها عطلة نهاية أسبوع).');

        if ($type->max_consecutive_days !== null) {
            $this->assert($days <= $type->max_consecutive_days, 'end_date', "الحد الأقصى لهذا النوع {$type->max_consecutive_days} يوماً.");
        }

        $this->assertNoOverlap($employee, $start, $end);

        if ($type->consumes_balance) {
            $balance = $this->balanceFor($employee, $type, $start->year);
            $this->assert(
                $balance !== null && $balance->remaining_days >= $days,
                'leave_type_id',
                'الرصيد غير كافٍ للفترة المطلوبة.'
            );
        }

        // التصعيد: طلب مديرٍ (لديه مرؤوسون) يُصعَّد للمستوى الأعلى.
        $isManager = $employee->subordinates()->exists();

        $leave = LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'days' => $days,
            'reason' => $data['reason'] ?? null,
            'attachment_path' => $data['attachment_path'] ?? null,
            'status' => 'pending',
            'is_escalated' => $isManager,
            'approver_id' => $employee->manager_id,
            'created_by' => $request->user()?->id,
        ]);

        $this->recordAudit($request, 'leave_requested', LeaveRequest::class, $leave->id, [
            'employee_id' => $employee->id, 'days' => $days, 'escalated' => $isManager,
        ]);

        return $leave;
    }

    /** اعتماد الطلب: خصم الرصيد + حدث تحديث الحضور. */
    public function approve(LeaveRequest $leave, Request $request): LeaveRequest
    {
        $this->assert($leave->isPending(), 'status', 'لا يمكن اعتماد طلب غير معلّق.');

        // فصل المهام: من قدّم الطلب لا يعتمده بنفسه (docs/05، docs/10 §2.2 التصعيد).
        $actorId = $request->user()?->id;
        abort_if($actorId !== null && $actorId === $leave->created_by, 403, 'لا يمكنك اعتماد طلب قدّمته بنفسك.');

        $type = $leave->leaveType;
        if ($type->consumes_balance) {
            $balance = $this->balanceFor($leave->employee, $type, $leave->start_date->year);
            $this->assert($balance !== null && $balance->remaining_days >= $leave->days, 'status', 'الرصيد لم يعد كافياً.');
            $balance->increment('consumed_days', $leave->days);
        }

        $leave->update([
            'status' => 'approved',
            'decided_by' => $request->user()?->id,
            'decided_at' => now(),
        ]);

        LeaveApproved::dispatch($leave);
        $this->recordAudit($request, 'leave_approved', LeaveRequest::class, $leave->id, [
            'employee_id' => $leave->employee_id, 'days' => $leave->days,
        ]);

        return $leave;
    }

    /** رفض الطلب مع سبب. */
    public function reject(LeaveRequest $leave, string $reason, Request $request): LeaveRequest
    {
        $this->assert($leave->isPending(), 'status', 'لا يمكن رفض طلب غير معلّق.');

        $leave->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'decided_by' => $request->user()?->id,
            'decided_at' => now(),
        ]);

        $this->recordAudit($request, 'leave_rejected', LeaveRequest::class, $leave->id, [
            'employee_id' => $leave->employee_id, 'reason' => $reason,
        ]);

        return $leave;
    }

    /** إلغاء الطلب: يعيد الرصيد إن كان معتمداً + حدث تصحيح الحضور. */
    public function cancel(LeaveRequest $leave, Request $request): LeaveRequest
    {
        $this->assert(in_array($leave->status, ['pending', 'approved'], true), 'status', 'لا يمكن إلغاء هذا الطلب.');

        $wasApproved = $leave->status === 'approved';
        if ($wasApproved && $leave->leaveType->consumes_balance) {
            $balance = $this->balanceFor($leave->employee, $leave->leaveType, $leave->start_date->year);
            $balance?->decrement('consumed_days', $leave->days);
        }

        $leave->update(['status' => 'cancelled']);

        if ($wasApproved) {
            LeaveCancelled::dispatch($leave);
        }
        $this->recordAudit($request, 'leave_cancelled', LeaveRequest::class, $leave->id, [
            'employee_id' => $leave->employee_id, 'restored' => $wasApproved,
        ]);

        return $leave;
    }

    /** عدد أيام العمل بين تاريخين (استبعاد عطلة نهاية الأسبوع). */
    public function workingDays(Carbon $start, Carbon $end): int
    {
        $days = 0;
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            if (! in_array($date->dayOfWeek, self::WEEKEND, true)) {
                $days++;
            }
        }

        return $days;
    }

    private function balanceFor(Employee $employee, LeaveType $type, int $year): ?LeaveBalance
    {
        return LeaveBalance::where('employee_id', $employee->id)
            ->where('leave_type_id', $type->id)
            ->where('year', $year)
            ->first();
    }

    private function assertNoOverlap(Employee $employee, Carbon $start, Carbon $end): void
    {
        $overlap = LeaveRequest::where('employee_id', $employee->id)
            ->whereIn('status', ['pending', 'approved'])
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->exists();

        $this->assert(! $overlap, 'start_date', 'تتداخل الفترة مع طلب إجازة قائم لنفس الموظف.');
    }

    /** يرمي ValidationException موحّداً عند فشل الشرط. */
    private function assert(bool $condition, string $field, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages([$field => [$message]]);
        }
    }
}
