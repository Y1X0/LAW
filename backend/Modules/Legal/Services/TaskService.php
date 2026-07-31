<?php

namespace Modules\Legal\Services;

use Illuminate\Http\Request;
use Modules\Core\Concerns\RecordsAudit;
use Modules\Legal\Models\CaseTask;

/**
 * منطق أعمال المهام (Legal / LC-5): إنشاء/تعديل/إسناد/إكمال — مع تدقيق.
 */
class TaskService
{
    use RecordsAudit;

    public function create(array $data, Request $request): CaseTask
    {
        $data['created_by'] = $request->user()?->id;
        $task = CaseTask::create($data);
        $this->recordAudit($request, 'task_created', CaseTask::class, $task->id, ['assigned_to' => $task->assigned_to]);

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

        return $task;
    }

    public function complete(CaseTask $task, Request $request): CaseTask
    {
        $task->update(['status' => 'done', 'completed_at' => now()]);
        $this->recordAudit($request, 'task_completed', CaseTask::class, $task->id);

        return $task;
    }
}
