import { useQuery } from '@tanstack/react-query'
import { formatCurrency } from '@/core/lib/format'
import { Badge, Button, Card } from '@/core/ui/primitives'
import { PageHeader, SectionCard } from '@/core/ui/section'
import { ErrorState, Skeleton } from '@/core/ui/states'
import { Reveal } from '@/core/ui/Reveal'
import { useCountUp } from '@/core/ui/useCountUp'
import { Icon, type IconName } from '@/core/layout/icons'
import { fetchDashboardSummary } from '@/dashboard/api/summary'

/** بطاقة مؤشّر عددي (KPI) — عدّاد متحرّك + ظهور متدرّج + لمسة ذهبية (كبقية اللوحات). */
function KpiCard({ value, label, icon, delay }: { value: number; label: string; icon: IconName; delay: number }) {
  const n = useCountUp(value)
  return (
    <Reveal delay={delay} className="h-full">
      <Card interactive className="relative h-full overflow-hidden">
        <span className="absolute inset-x-0 top-0 h-[3px] bg-gradient-to-l from-transparent via-gold-400 to-transparent" />
        <div className="flex items-start justify-between gap-3">
          <div>
            <div className="text-[32px] font-extrabold leading-none tabular-nums text-brand-700">{n}</div>
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
    </Reveal>
  )
}

/** بطاقة مؤشّر مالي — قيمة مُنسّقة مسبقاً من الخادم (لا حساب في الواجهة). */
function MoneyStat({ label, value, tone = 'slate' }: { label: string; value: string; tone?: 'green' | 'amber' | 'slate' | 'navy' }) {
  const tones: Record<string, string> = {
    green: 'text-green-700',
    amber: 'text-amber-700',
    slate: 'text-slate-800',
    navy: 'text-brand-700',
  }
  return (
    <Card className="p-4">
      <div className="text-xs text-slate-500">{label}</div>
      <div className={`mt-1 text-xl font-bold tabular-nums ${tones[tone]}`}>{value}</div>
    </Card>
  )
}

/** سطر توزيع «تسمية: قيمة» بنبرة لونية. */
function DistRow({ label, value, tone = 'slate' }: { label: string; value: number; tone?: 'green' | 'amber' | 'slate' | 'navy' }) {
  return (
    <div className="flex items-center justify-between py-1.5 text-sm">
      <span className="text-slate-600">{label}</span>
      <Badge tone={tone}>{value}</Badge>
    </div>
  )
}

/**
 * لوحة المؤشّرات الإدارية الشاملة (Phase 7 · PR-2) — تعكس `GET /api/dashboard/summary`
 * (قراءة فقط): مؤشّرات المكتب عبر القانون/العملاء/الموارد البشرية/المالية. كل القيم
 * تأتي مُجمَّعة من الخادم؛ الواجهة تعرض فقط ولا تحسب أي مجموع.
 */
export function ManagementDashboardPage() {
  const { data, isPending, isError, error, refetch } = useQuery({
    queryKey: ['dashboard', 'summary'],
    queryFn: fetchDashboardSummary,
  })

  return (
    <div className="space-y-5">
      <PageHeader title="المؤشّرات الإدارية" subtitle="نظرة شاملة على أداء المكتب" />

      {isPending ? (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4" data-testid="dashboard-summary-skeleton">
          {Array.from({ length: 4 }).map((_, i) => (
            <Card key={i}>
              <Skeleton className="h-8 w-16" />
              <Skeleton className="mt-3 h-4 w-24" />
            </Card>
          ))}
        </div>
      ) : isError ? (
        <ErrorState error={error}>
          <div className="mt-3">
            <Button onClick={() => void refetch()}>إعادة المحاولة</Button>
          </div>
        </ErrorState>
      ) : (
        <>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <KpiCard label="إجمالي القضايا" value={data.legal.cases_total} icon="cases" delay={0} />
            <KpiCard label="العملاء النشطون" value={data.clients.active} icon="users" delay={70} />
            <KpiCard label="الموظفون النشطون" value={data.hr.employees_active} icon="profile" delay={140} />
            <KpiCard label="الجلسات القادمة" value={data.legal.hearings_upcoming} icon="leave" delay={210} />
          </div>

          <SectionCard title="المؤشّرات المالية">
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
              <MoneyStat label="الإيرادات" value={formatCurrency(data.finance.revenue, 'SAR')} tone="green" />
              <MoneyStat label="المصروفات" value={formatCurrency(data.finance.expenses, 'SAR')} tone="amber" />
              <MoneyStat label="صافي الدخل" value={formatCurrency(data.finance.net, 'SAR')} tone="navy" />
              <MoneyStat label="المستحقات" value={formatCurrency(data.finance.outstanding, 'SAR')} tone="navy" />
            </div>
          </SectionCard>

          <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <SectionCard title="القضايا">
              <DistRow label="مفتوحة" value={data.legal.cases_open} tone="green" />
              <DistRow label="مغلقة" value={data.legal.cases_closed} tone="slate" />
            </SectionCard>

            <SectionCard title="العملاء">
              <DistRow label="نشطون" value={data.clients.active} tone="green" />
              <DistRow label="الإجمالي" value={data.clients.total} tone="slate" />
            </SectionCard>

            <SectionCard title="متابعة">
              <DistRow label="مهام متأخّرة" value={data.legal.tasks_overdue} tone={data.legal.tasks_overdue > 0 ? 'amber' : 'slate'} />
              <DistRow label="فواتير متأخّرة" value={data.finance.invoices_overdue} tone={data.finance.invoices_overdue > 0 ? 'amber' : 'slate'} />
              <DistRow label="جلسات قادمة" value={data.legal.hearings_upcoming} tone="navy" />
            </SectionCard>
          </div>
        </>
      )}
    </div>
  )
}
