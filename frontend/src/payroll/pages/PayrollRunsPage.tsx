import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Badge, Button, Card, SelectField } from '@/core/ui/primitives'
import { PageHeader, SectionCard } from '@/core/ui/section'
import { EmptyState, ErrorState, Skeleton } from '@/core/ui/states'
import { useToast } from '@/core/ui/useToast'
import { ApiError } from '@/core/api/types'
import { fetchPeriods, monthLabel, periodStatusLabel } from '@/payroll/api/payroll'
import { createRun, fetchPeriodRuns, runStatusLabel, runStatusTone } from '@/payroll/api/payrollRuns'
import { RunWorkflowPanel } from './components/RunWorkflowPanel'

/**
 * مسيرات الرواتب (Phase 2 / PR-4) — اختيار فترة، إنشاء/اختيار مسير، ثم تشغيل دورته
 * (لقطات → احتساب → اعتماد → قفل) عبر نقاط النهاية الموجودة فقط. لا منطق جديد.
 */
export function PayrollRunsPage() {
  const qc = useQueryClient()
  const { show } = useToast()
  const [periodId, setPeriodId] = useState<number | ''>('')
  const [runId, setRunId] = useState<number | null>(null)

  const periods = useQuery({ queryKey: ['payroll', 'periods-select'], queryFn: () => fetchPeriods({ perPage: 100 }) })
  const runs = useQuery({
    queryKey: ['payroll', 'period-runs', periodId],
    queryFn: () => fetchPeriodRuns(Number(periodId)),
    enabled: periodId !== '',
  })

  const create = useMutation({
    mutationFn: () => createRun(Number(periodId)),
    onSuccess: (run) => {
      show('تم إنشاء المسير')
      void qc.invalidateQueries({ queryKey: ['payroll', 'period-runs', periodId] })
      setRunId(run.id)
    },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّر إنشاء المسير', 'error'),
  })

  return (
    <div className="space-y-5">
      <PageHeader
        title="مسيرات الرواتب"
        subtitle="تشغيل دورة الرواتب لكل فترة"
      />

      {runId ? (
        <RunWorkflowPanel runId={runId} onBack={() => setRunId(null)} />
      ) : (
        <>
          <Card>
            <SelectField
              label="الفترة"
              value={periodId === '' ? '' : String(periodId)}
              onChange={(e) => { setPeriodId(e.target.value ? Number(e.target.value) : ''); setRunId(null) }}
            >
              <option value="">— اختر فترة —</option>
              {periods.data?.items.map((p) => (
                <option key={p.id} value={p.id}>
                  {monthLabel(p.month)} {p.year} — {p.branch?.name ?? 'كل الفروع'} ({periodStatusLabel(p.status)})
                </option>
              ))}
            </SelectField>
          </Card>

          {periodId === '' ? (
            <EmptyState message="اختر فترة لعرض مسيراتها أو إنشاء مسير جديد." />
          ) : (
            <SectionCard
              title="مسيرات الفترة"
              action={<Button onClick={() => create.mutate()} disabled={create.isPending}>{create.isPending ? 'جارٍ…' : 'إنشاء مسير'}</Button>}
            >
              {runs.isPending ? (
                <Skeleton className="h-16 w-full" />
              ) : runs.isError ? (
                <ErrorState error={runs.error}><div className="mt-3"><Button onClick={() => void runs.refetch()}>إعادة المحاولة</Button></div></ErrorState>
              ) : runs.data.length === 0 ? (
                <EmptyState message="لا توجد مسيرات لهذه الفترة. أنشئ مسيراً للبدء." />
              ) : (
                <ul className="space-y-2.5">
                  {runs.data.map((r) => (
                    <li key={r.id}>
                      <Card interactive className="flex items-center justify-between gap-3 p-3.5">
                        <button type="button" onClick={() => setRunId(r.id)} className="lp-press flex-1 text-right">
                          <span className="font-bold text-brand-700 tabular-nums">مسير #{r.id}</span>
                        </button>
                        <div className="flex items-center gap-2">
                          <Badge tone={runStatusTone(r.status)}>{runStatusLabel(r.status)}</Badge>
                          <Button variant="ghost" onClick={() => setRunId(r.id)}>فتح</Button>
                        </div>
                      </Card>
                    </li>
                  ))}
                </ul>
              )}
            </SectionCard>
          )}
        </>
      )}
    </div>
  )
}
