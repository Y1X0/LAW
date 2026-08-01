import { useState } from 'react'
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { Badge, Button, Card } from '@/core/ui/primitives'
import { PageHeader } from '@/core/ui/section'
import { Tabs } from '@/core/ui/Tabs'
import { EmptyState, ErrorState, Skeleton } from '@/core/ui/states'
import { useToast } from '@/core/ui/useToast'
import { formatDate } from '@/core/lib/format'
import { taskPriorityLabel } from '@/lawyer/api/caseFile'
import { completeTask, fetchTasks, type Task, type TaskStatus } from '@/lawyer/api/tasks'

const PER_PAGE = 15

const TABS: { key: TaskStatus; label: string }[] = [
  { key: 'open', label: 'المهام المعلّقة' },
  { key: 'done', label: 'المهام المكتملة' },
]

function priorityTone(p: string): 'green' | 'amber' | 'slate' | 'navy' {
  if (p === 'high') return 'amber'
  if (p === 'low') return 'slate'
  return 'navy'
}

/**
 * مهام المحامي (LP-5) — تبويبا المعلّقة/المكتملة عبر فلتر status الخادمي،
 * وإكمال المهمة (PATCH) مع منع التكرار وتحديث مباشر. الخادم يبقى الحكم (403).
 */
export function TasksPage() {
  const qc = useQueryClient()
  const { show } = useToast()
  const [tab, setTab] = useState<TaskStatus>('open')
  const [page, setPage] = useState(1)

  const query = useQuery({
    queryKey: ['tasks', { status: tab, page }],
    queryFn: () => fetchTasks(tab, page, PER_PAGE),
    placeholderData: keepPreviousData,
  })

  const complete = useMutation({
    mutationFn: (id: number) => completeTask(id),
    onSuccess: () => {
      show('تم إكمال المهمة')
      void qc.invalidateQueries({ queryKey: ['tasks'] })
    },
    onError: () => show('تعذّر إكمال المهمة', 'error'),
  })

  function switchTab(next: TaskStatus) {
    setPage(1)
    setTab(next)
  }

  return (
    <div className="space-y-5">
      <PageHeader title="المهام" subtitle="مهامك المسندة" />

      {/* تبويبات الحالة — مؤشّر ذهبي موحّد */}
      <Tabs tabs={TABS} active={tab} onChange={(k) => switchTab(k as TaskStatus)} />

      {query.isPending ? (
        <Card>
          <div data-testid="tasks-skeleton" className="space-y-3">
            {Array.from({ length: 5 }).map((_, i) => (
              <Skeleton key={i} className="h-9 w-full" />
            ))}
          </div>
        </Card>
      ) : query.isError ? (
        <ErrorState error={query.error}>
          <div className="mt-3">
            <Button onClick={() => void query.refetch()}>إعادة المحاولة</Button>
          </div>
        </ErrorState>
      ) : query.data.items.length === 0 ? (
        <Card>
          <EmptyState message={tab === 'open' ? 'لا توجد مهام معلّقة.' : 'لا توجد مهام مكتملة.'} />
        </Card>
      ) : (
        <>
          <Card className="lp-table-wrap p-0">
            <table
              className={`lp-table min-w-[720px] text-right text-sm transition-opacity ${
                query.isFetching ? 'opacity-60' : ''
              }`}
              aria-busy={query.isFetching}
            >
              <thead>
                <tr className="text-xs">
                  <th className="px-4 py-3 text-right">المهمة</th>
                  <th className="px-4 py-3 text-right">القضية</th>
                  <th className="px-4 py-3 text-right">الأولوية</th>
                  <th className="px-4 py-3 text-right">الاستحقاق</th>
                  <th className="px-4 py-3 text-right">{tab === 'open' ? 'إجراء' : 'أُنجزت في'}</th>
                </tr>
              </thead>
              <tbody>
                {query.data.items.map((t) => (
                  <TaskRow
                    key={t.id}
                    t={t}
                    tab={tab}
                    completing={complete.isPending && complete.variables === t.id}
                    onComplete={() => complete.mutate(t.id)}
                  />
                ))}
              </tbody>
            </table>
          </Card>

          <div className="flex items-center justify-between gap-3 text-sm text-slate-600">
            <span className="tabular-nums">
              صفحة {query.data.meta.page} من {query.data.meta.total_pages} · {query.data.meta.total} مهمة
            </span>
            <div className="flex gap-2">
              <Button variant="ghost" onClick={() => setPage((p) => Math.max(1, p - 1))} disabled={page <= 1 || query.isFetching}>
                السابق
              </Button>
              <Button
                variant="ghost"
                onClick={() => setPage((p) => Math.min(query.data.meta.total_pages, p + 1))}
                disabled={page >= query.data.meta.total_pages || query.isFetching}
              >
                التالي
              </Button>
            </div>
          </div>
        </>
      )}
    </div>
  )
}

function TaskRow({
  t,
  tab,
  completing,
  onComplete,
}: {
  t: Task
  tab: TaskStatus
  completing: boolean
  onComplete: () => void
}) {
  return (
    <tr className="border-b border-slate-100 last:border-0">
      <td className="px-4 py-3 font-medium text-slate-800">{t.title}</td>
      <td className="px-4 py-3">
        {t.case ? (
          <Link to={`/cases/${t.case.id}`} className="text-brand-700 hover:underline">
            <span className="tabular-nums">{t.case.internal_number}</span>
          </Link>
        ) : (
          '—'
        )}
      </td>
      <td className="px-4 py-3">
        <Badge tone={priorityTone(t.priority)}>{taskPriorityLabel(t.priority)}</Badge>
      </td>
      <td className="px-4 py-3 tabular-nums text-slate-600">{formatDate(t.due_date ?? null)}</td>
      <td className="px-4 py-3">
        {tab === 'open' ? (
          <Button onClick={onComplete} disabled={completing}>
            {completing ? 'جارٍ الإكمال…' : 'إكمال'}
          </Button>
        ) : (
          <span className="tabular-nums text-slate-500">{formatDate(t.completed_at ?? null)}</span>
        )}
      </td>
    </tr>
  )
}
