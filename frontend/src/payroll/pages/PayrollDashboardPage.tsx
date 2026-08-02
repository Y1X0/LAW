import { useQuery } from '@tanstack/react-query'
import { Badge, Button, Card } from '@/core/ui/primitives'
import { PageHeader, SectionCard } from '@/core/ui/section'
import { EmptyState, ErrorState, Skeleton } from '@/core/ui/states'
import { Icon, type IconName } from '@/core/layout/icons'
import { formatCurrency, formatPeriod } from '@/core/lib/format'
import {
  fetchPayrollCostTotals,
  fetchRecentPeriods,
  periodStatusLabel,
  periodStatusTone,
} from '@/payroll/api/payroll'

/**
 * لوحة الرواتب (Phase 2 / PR-1) — نظرة عامة من نقاط النهاية الموجودة فقط:
 * الإجماليات من payroll-reports/cost (نتائج مجمّدة)، وأحدث الفترات من payroll-periods.
 * لا روابط لشاشات لم تُبْنَ بعد (تُضاف في PRs التالية) — بلا روابط معطّلة.
 */
export function PayrollDashboardPage() {
  const totals = useQuery({ queryKey: ['payroll', 'cost-totals'], queryFn: fetchPayrollCostTotals })
  const periods = useQuery({ queryKey: ['payroll', 'recent-periods'], queryFn: () => fetchRecentPeriods(6) })

  const isPending = totals.isPending || periods.isPending
  const isError = totals.isError || periods.isError

  return (
    <div className="space-y-5">
      <PageHeader title="الرواتب" subtitle="نظرة عامة على تكلفة الرواتب وأحدث الفترات" />

      {isPending ? (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3" data-testid="payroll-dashboard-skeleton">
          {Array.from({ length: 6 }).map((_, i) => (
            <Card key={i}>
              <Skeleton className="h-8 w-28" />
              <Skeleton className="mt-3 h-4 w-24" />
            </Card>
          ))}
        </div>
      ) : isError ? (
        <ErrorState error={totals.error ?? periods.error}>
          <div className="mt-3">
            <Button onClick={() => { void totals.refetch(); void periods.refetch() }}>إعادة المحاولة</Button>
          </div>
        </ErrorState>
      ) : (
        <>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <StatCard label="إجمالي الرواتب" value={formatCurrency(totals.data.gross, 'SAR')} icon="salary" />
            <StatCard label="إجمالي الإضافات" value={formatCurrency(totals.data.allowances, 'SAR')} icon="salary" />
            <StatCard label="إجمالي الخصومات" value={formatCurrency(totals.data.deductions, 'SAR')} icon="salary" />
            <StatCard label="صافي الرواتب" value={formatCurrency(totals.data.net, 'SAR')} icon="salary" />
            <StatCard label="الموظفون المشمولون" value={String(totals.data.headcount)} icon="profile" />
            <StatCard label="المسيرات المحسوبة" value={String(totals.data.runs)} icon="data" />
          </div>

          <SectionCard title="أحدث الفترات">
            {periods.data.length === 0 ? (
              <EmptyState message="لا توجد فترات رواتب بعد." />
            ) : (
              <ul className="space-y-2.5">
                {periods.data.map((p) => (
                  <li key={p.id}>
                    <Card className="flex flex-col gap-3 p-3.5 sm:flex-row sm:items-center sm:justify-between">
                      <div className="min-w-0">
                        <div className="font-bold text-brand-700 tabular-nums">{formatPeriod(p.year, p.month)}</div>
                        <div className="mt-0.5 text-xs text-slate-500">{p.branch?.name ?? 'كل الفروع'}</div>
                      </div>
                      <div className="flex flex-wrap items-center gap-2 text-sm text-slate-600">
                        <span className="tabular-nums">عدد المسيرات: {p.runs_count}</span>
                        <Badge tone={periodStatusTone(p.status)}>{periodStatusLabel(p.status)}</Badge>
                      </div>
                    </Card>
                  </li>
                ))}
              </ul>
            )}
          </SectionCard>
        </>
      )}
    </div>
  )
}

function StatCard({ label, value, icon }: { label: string; value: string; icon: IconName }) {
  return (
    <Card className="relative h-full overflow-hidden">
      <span className="absolute inset-x-0 top-0 h-[3px] bg-gradient-to-l from-transparent via-gold-400 to-transparent" />
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0">
          <div className="truncate text-2xl font-extrabold leading-tight tabular-nums text-brand-700">{value}</div>
          <div className="mt-2 text-sm text-slate-500">{label}</div>
        </div>
        <span
          aria-hidden="true"
          className="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-brand-50 text-brand-600"
        >
          <Icon name={icon} className="h-5 w-5" />
        </span>
      </div>
    </Card>
  )
}
