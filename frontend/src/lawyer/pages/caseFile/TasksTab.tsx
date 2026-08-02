import { useQuery } from '@tanstack/react-query'
import { Badge } from '@/core/ui/primitives'
import { formatDate } from '@/core/lib/format'
import { fetchCaseTasks, taskPriorityLabel, taskStatusLabel } from '@/lawyer/api/caseFile'
import { AsyncTab, DataTable } from './AsyncTab'

function priorityTone(p: string): 'green' | 'amber' | 'slate' | 'navy' {
  if (p === 'high') return 'amber'
  if (p === 'low') return 'slate'
  return 'navy'
}

/** التبويب 7 — مهام القضية (يتطلّب tasks.view_own — 403 يُعالَج بحالة صلاحية واضحة). */
export function TasksTab({ caseId }: { caseId: string }) {
  const q = useQuery({ queryKey: ['case', caseId, 'tasks'], queryFn: () => fetchCaseTasks(caseId) })

  return (
    <AsyncTab
      q={q}
      emptyMessage="لا توجد مهام مرتبطة بهذه القضية."
      render={(tasks) => (
        <DataTable head={['المهمة', 'الأولوية', 'الاستحقاق', 'الحالة', 'المكلّف']}>
          {tasks.map((t) => (
            <tr key={t.id} className="border-b border-slate-100 last:border-0">
              <td data-label="المهمة" className="px-4 py-3 font-medium text-slate-800">{t.title}</td>
              <td data-label="الأولوية" className="px-4 py-3">
                <Badge tone={priorityTone(t.priority)}>{taskPriorityLabel(t.priority)}</Badge>
              </td>
              <td data-label="الاستحقاق" className="px-4 py-3 tabular-nums text-slate-600">{formatDate(t.due_date ?? null)}</td>
              <td data-label="الحالة" className="px-4 py-3">
                <Badge tone={t.status === 'done' ? 'green' : 'slate'}>{taskStatusLabel(t.status)}</Badge>
              </td>
              <td data-label="المكلّف" className="px-4 py-3 text-slate-600">{t.assignee?.full_name_ar ?? '—'}</td>
            </tr>
          ))}
        </DataTable>
      )}
    />
  )
}
