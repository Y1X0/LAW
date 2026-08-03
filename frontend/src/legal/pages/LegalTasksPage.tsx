import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Badge, Button, Card, SelectField } from '@/core/ui/primitives'
import { PageHeader } from '@/core/ui/section'
import { EmptyState, ErrorState, Skeleton } from '@/core/ui/states'
import { useToast } from '@/core/ui/useToast'
import { ApiError } from '@/core/api/types'
import {
  TASK_PRIORITIES,
  TASK_STATUSES,
  type Task,
  completeTask,
  fetchTasks,
  taskPriorityLabel,
  taskPriorityTone,
  taskStatusLabel,
  taskStatusTone,
} from '@/legal/api/tasks'
import { TaskFormModal } from './components/TaskFormModal'
import { AssignTaskModal } from './components/AssignTaskModal'

/**
 * إشراف المهام (Phase 3 / PR-6) — كل المهام (tasks.view_all) من `GET /tasks`،
 * مع فلاتر (حالة/أولوية) وترقيم، وإنشاء/إعادة إسناد/إكمال/تعديل عبر النقاط الموجودة.
 * الحالات open/done فقط. صفر تغيير خلفي.
 */
export function LegalTasksPage() {
  const [status, setStatus] = useState('')
  const [priority, setPriority] = useState('')
  const [page, setPage] = useState(1)
  const [creating, setCreating] = useState(false)

  const query = useQuery({
    queryKey: ['legal', 'tasks', { status, priority, page }],
    queryFn: () => fetchTasks({ status: status || undefined, priority: priority || undefined, page }),
  })

  const onStatus = (e: { target: { value: string } }) => { setStatus(e.target.value); setPage(1) }
  const onPriority = (e: { target: { value: string } }) => { setPriority(e.target.value); setPage(1) }

  return (
    <div className="space-y-5">
      <PageHeader title="المهام" subtitle="متابعة مهام المكتب وإسنادها" action={<Button onClick={() => setCreating(true)}>إنشاء مهمة</Button>} />

      <Card className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <SelectField label="الحالة" value={status} onChange={onStatus}>
          <option value="">كل الحالات</option>
          {TASK_STATUSES.map((s) => <option key={s} value={s}>{taskStatusLabel(s)}</option>)}
        </SelectField>
        <SelectField label="الأولوية" value={priority} onChange={onPriority}>
          <option value="">كل الأولويات</option>
          {TASK_PRIORITIES.map((p) => <option key={p} value={p}>{taskPriorityLabel(p)}</option>)}
        </SelectField>
      </Card>

      {query.isPending ? (
        <div className="space-y-2.5" data-testid="tasks-skeleton">
          {Array.from({ length: 4 }).map((_, i) => (
            <Card key={i}><Skeleton className="h-5 w-48" /><Skeleton className="mt-2 h-4 w-32" /></Card>
          ))}
        </div>
      ) : query.isError ? (
        <ErrorState error={query.error}><div className="mt-3"><Button onClick={() => void query.refetch()}>إعادة المحاولة</Button></div></ErrorState>
      ) : query.data.items.length === 0 ? (
        <EmptyState message="لا توجد مهام مطابقة." />
      ) : (
        <>
          <ul className="space-y-2.5" aria-busy={query.isFetching}>
            {query.data.items.map((t) => <TaskRow key={t.id} task={t} />)}
          </ul>

          {query.data.meta.total_pages > 1 && (
            <div className="flex items-center justify-between gap-3 text-sm text-slate-600">
              <Button variant="ghost" onClick={() => setPage((p) => Math.max(1, p - 1))} disabled={query.data.meta.page <= 1}>السابق</Button>
              <span className="tabular-nums">صفحة {query.data.meta.page} من {query.data.meta.total_pages}</span>
              <Button variant="ghost" onClick={() => setPage((p) => p + 1)} disabled={query.data.meta.page >= query.data.meta.total_pages}>التالي</Button>
            </div>
          )}
        </>
      )}

      {creating && <TaskFormModal onClose={() => setCreating(false)} />}
    </div>
  )
}

function TaskRow({ task }: { task: Task }) {
  const qc = useQueryClient()
  const { show } = useToast()
  const [editing, setEditing] = useState(false)
  const [assigning, setAssigning] = useState(false)
  const done = task.status === 'done'

  const complete = useMutation({
    mutationFn: () => completeTask(task.id),
    onSuccess: () => { show('تم إكمال المهمة'); void qc.invalidateQueries({ queryKey: ['legal', 'tasks'] }) },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّر الإكمال', 'error'),
  })

  return (
    <li>
      <Card className="flex flex-col gap-3 p-3.5 sm:flex-row sm:items-center sm:justify-between">
        <div className="min-w-0">
          <div className="font-medium text-slate-800">{task.title}</div>
          <div className="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-slate-500">
            <span>{task.assignee?.full_name_ar ?? 'غير مُسنَدة'}</span>
            {task.case && <span className="text-slate-400">{task.case.internal_number}</span>}
            {task.due_date && <span className="tabular-nums text-slate-400">استحقاق: {task.due_date}</span>}
          </div>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <Badge tone={taskPriorityTone(task.priority)}>{taskPriorityLabel(task.priority)}</Badge>
          <Badge tone={taskStatusTone(task.status)}>{taskStatusLabel(task.status)}</Badge>
          {done ? (
            <span className="text-xs text-slate-400">مكتملة</span>
          ) : (
            <>
              <Button variant="ghost" onClick={() => setEditing(true)}>تعديل</Button>
              <Button variant="ghost" onClick={() => setAssigning(true)}>إعادة إسناد</Button>
              <Button variant="ghost" onClick={() => complete.mutate()} disabled={complete.isPending}>{complete.isPending ? 'جارٍ…' : 'إكمال'}</Button>
            </>
          )}
        </div>
      </Card>
      {editing && <TaskFormModal existing={task} onClose={() => setEditing(false)} />}
      {assigning && <AssignTaskModal taskId={task.id} onClose={() => setAssigning(false)} />}
    </li>
  )
}
