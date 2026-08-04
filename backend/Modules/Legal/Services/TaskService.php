<?php

namespace Modules\Legal\Services;

use Illuminate\Http\Request;
use Modules\Core\Concerns\RecordsAudit;
use Modules\HR\Models\Employee;
use Modules\Legal\Models\CaseTask;
use Modules\Notifications\Concerns\NotifiesUsers;

/**
 * منطق أعمال المهام (Legal / LC-5): إنشاء/تعديل/إسناد/إكمال — مع تدقيق.
 */
class TaskService
{
    use NotifiesUsers, RecordsAudit;

    public function create(array $data, Request $request): CaseTask
    {
        $data['created_by'] = $request->user()?->id;
        $task = CaseTask::create($data);
        $this->recordAudit($request, 'task_created', CaseTask::class, $task->id, ['assigned_to' => $task->assigned_to]);
        // مهمة أُنشئت مُسنَدة → إشعار المُسنَد إليه (ما لم يكن هو المُنشئ نفسه).
        $this->notifyAssignee($task, $request);

        return $task;
    }

    public function update(CaseTask $task, array $data, Request $request): CaseTask
    {
        $task->update($data);
        $this->recordAudit($request, 'task_updated', CaseTask::class, $task->id);

        return $task;
    }

    public function assign(CaseTask $task, int $employeeId, Request $request): CaseTask
    {
        $task->update(['assigned_to' => $employeeId]);
        $this->recordAudit($request, 'task_reassigned', CaseTask::class, $task->id, ['assigned_to' => $employeeId]);
        // إشعار المُسنَد إليه الجديد (ما لم يكن هو المُسنِد نفسه).
        $this->notifyAssignee($task, $request);

        return $task;
    }

    public function complete(CaseTask $task, Request $request): CaseTask
    {
        $task->update(['status' => 'done', 'completed_at' => now()]);
        $this->recordAudit($request, 'task_completed', CaseTask::class, $task->id);
        // إشعار مُنشئ المهمة باكتمالها (ما لم يكن هو من أكملها).
        $actorId = $request->user()?->id;
        if ($task->created_by !== null && $task->created_by !== $actorId) {
            $this->notify($task->created_by, 'task_completed', 'اكتملت مهمة أنشأتها', $task->title, 'CaseTask', $task->id);
        }

        return $task;
    }

    /** يُشعر المُسنَد إليه (حساب الموظف المرتبط) بالمهمة — ما لم يكن هو الفاعل نفسه أو بلا حساب. */
    private function notifyAssignee(CaseTask $task, Request $request): void
    {
        if ($task->assigned_to === null) {
            return;
        }

        $recipientUserId = Employee::find($task->assigned_to)?->user_id;
        if ($recipientUserId !== null && $recipientUserId !== $request->user()?->id) {
            $this->notify($recipientUserId, 'task_assigned', 'أُسندت إليك مهمة', $task->title, 'CaseTask', $task->id);
        }
    }
}
