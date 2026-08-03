import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useParams } from 'react-router-dom'
import { Badge, Button, Card } from '@/core/ui/primitives'
import { PageHeader } from '@/core/ui/section'
import { EmptyState, ErrorState, Skeleton } from '@/core/ui/states'
import { useToast } from '@/core/ui/useToast'
import { formatCurrency } from '@/core/lib/format'
import { fetchRun, runStatusLabel, runStatusTone } from '@/payroll/api/payrollRuns'
import { fetchRunPayslips, printPayslip } from '@/payroll/api/payslips'
import { PayslipDetailModal } from './components/PayslipDetailModal'

/**
 * كشوف مسير (Phase 2 / PR-5) — عرض/طباعة كشوف الرواتب من النتائج المجمّدة فقط.
 * لا PDF خادمي ولا إعادة توليد (موثّق في Backlog).
 */
export function PayrollPayslipsPage() {
  const { runId: runIdParam } = useParams()
  const runId = Number(runIdParam)
  const { show } = useToast()
  const [viewing, setViewing] = useState<number | null>(null)

  const run = useQuery({ queryKey: ['payroll', 'run', runId], queryFn: () => fetchRun(runId), enabled: Number.isFinite(runId) })
  const payslips = useQuery({ queryKey: ['payroll', 'run-payslips', runId], queryFn: () => fetchRunPayslips(runId), enabled: Number.isFinite(runId) })

  async function onPrint(itemId: number) {
    try {
      await printPayslip(itemId)
    } catch (e) {
      show(e instanceof Error ? e.message : 'تعذّرت الطباعة', 'error')
    }
  }

  return (
    <div className="space-y-5">
      <PageHeader
        title="كشوف الرواتب"
        subtitle={run.data ? `مسير #${run.data.id}` : 'كشوف المسير'}
        action={run.data ? <Badge tone={runStatusTone(run.data.status)}>{runStatusLabel(run.data.status)}</Badge> : undefined}
      />

      {payslips.isPending ? (
        <div className="space-y-2.5">
          {Array.from({ length: 4 }).map((_, i) => (
            <Card key={i}><Skeleton className="h-5 w-40" /><Skeleton className="mt-2 h-4 w-24" /></Card>
          ))}
        </div>
      ) : payslips.isError ? (
        <ErrorState error={payslips.error}><div className="mt-3"><Button onClick={() => void payslips.refetch()}>إعادة المحاولة</Button></div></ErrorState>
      ) : payslips.data.length === 0 ? (
        <EmptyState message="لا توجد كشوف لهذا المسير. نفّذ الاحتساب أولاً من صفحة المسيرات." />
      ) : (
        <ul className="space-y-2.5">
          {payslips.data.map((p) => (
            <li key={p.payroll_item_id}>
              <Card className="flex flex-col gap-3 p-3.5 sm:flex-row sm:items-center sm:justify-between">
                <div className="min-w-0">
                  <div className="font-bold text-slate-800">{p.employee?.name ?? '—'}</div>
                  <div className="mt-0.5 tabular-nums text-xs text-slate-400">{p.employee?.employee_number ?? ''}</div>
                </div>
                <div className="flex flex-wrap items-center gap-3">
                  <div className="text-sm tabular-nums text-slate-600">
                    الصافي: <span className="font-bold text-brand-700">{formatCurrency(p.net, p.currency)}</span>
                  </div>
                  <Button variant="ghost" onClick={() => setViewing(p.payroll_item_id)}>عرض</Button>
                  <Button variant="ghost" onClick={() => void onPrint(p.payroll_item_id)}>طباعة</Button>
                </div>
              </Card>
            </li>
          ))}
        </ul>
      )}

      {viewing != null && <PayslipDetailModal itemId={viewing} onClose={() => setViewing(null)} />}
    </div>
  )
}
