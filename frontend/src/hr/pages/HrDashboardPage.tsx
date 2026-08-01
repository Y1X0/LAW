import { useQuery } from '@tanstack/react-query'
import { Button, Card } from '@/core/ui/primitives'
import { PageHeader } from '@/core/ui/section'
import { ErrorState, Skeleton } from '@/core/ui/states'
import { Reveal } from '@/core/ui/Reveal'
import { useCountUp } from '@/core/ui/useCountUp'
import { Icon, type IconName } from '@/core/layout/icons'
import { fetchHrDashboardStats } from '@/hr/api/dashboard'

/** بطاقة مؤشّر (KPI) — عدّاد متحرّك + ظهور متدرّج + لمسة ذهبية + رفعة عند المرور (كـ UP-4). */
function KpiCard({
  value,
  label,
  icon,
  delay,
}: {
  value: number | null
  label: string
  icon: IconName
  delay: number
}) {
  const n = useCountUp(value ?? 0)
  return (
    <Reveal delay={delay} className="h-full">
      <Card interactive className="relative h-full overflow-hidden">
        <span className="absolute inset-x-0 top-0 h-[3px] bg-gradient-to-l from-transparent via-gold-400 to-transparent" />
        <div className="flex items-start justify-between gap-3">
          <div>
            <div className="text-[32px] font-extrabold leading-none tabular-nums text-brand-700">
              {value == null ? '—' : n}
            </div>
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

/**
 * لوحة الموارد البشرية (HR-2) — 3 مؤشّرات فقط من APIs موجودة (بلا باك-إند جديد):
 * عدد الموظفين · إجازات معلّقة · غياب اليوم. التحية تظهر في شريط الهيكل العلوي.
 */
export function HrDashboardPage() {
  const { data, isPending, isError, error, refetch } = useQuery({
    queryKey: ['hr', 'dashboard'],
    queryFn: fetchHrDashboardStats,
  })

  return (
    <div className="space-y-5">
      <PageHeader title="الموارد البشرية" subtitle="نظرة سريعة على حالة المكتب اليوم" />

      {isPending ? (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3" data-testid="hr-dashboard-skeleton">
          {Array.from({ length: 3 }).map((_, i) => (
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
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <KpiCard label="الموظفون" value={data.employees} icon="profile" delay={0} />
          <KpiCard label="إجازات معلّقة" value={data.pendingLeave} icon="leave" delay={70} />
          <KpiCard label="غياب اليوم" value={data.todayAbsent} icon="attendance" delay={140} />
        </div>
      )}
    </div>
  )
}
