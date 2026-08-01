import { useQuery } from '@tanstack/react-query'
import { Badge, Button, Card } from '@/core/ui/primitives'
import { PageHeader, SectionCard } from '@/core/ui/section'
import { ErrorState, Skeleton } from '@/core/ui/states'
import { Reveal } from '@/core/ui/Reveal'
import { useCountUp } from '@/core/ui/useCountUp'
import { Icon, type IconName } from '@/core/layout/icons'
import { activityLabel, fetchAdminSummary } from '@/admin/api/summary'

/** بطاقة مؤشّر (KPI) — عدّاد متحرّك + ظهور متدرّج + لمسة ذهبية (كبقية اللوحات). */
function KpiCard({ value, label, icon, delay }: { value: number | null; label: string; icon: IconName; delay: number }) {
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
 * لوحة نظام وحدة التحكّم (ADMIN-4) — إحصاءات المنصّة من `GET /admin/summary`
 * (للقراءة فقط): مؤشّرات، توزيع الحالات، تنبيهات، وآخر نشاط النظام.
 */
export function AdminHomePage() {
  const { data, isPending, isError, error, refetch } = useQuery({
    queryKey: ['admin', 'summary'],
    queryFn: fetchAdminSummary,
  })

  return (
    <div className="space-y-5">
      <PageHeader title="وحدة التحكّم" subtitle="نظرة عامة على حالة المنصّة" />

      {isPending ? (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4" data-testid="admin-summary-skeleton">
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
            <KpiCard label="المستخدمون" value={data.users.total} icon="users" delay={0} />
            <KpiCard label="الموظفون" value={data.employees.total} icon="profile" delay={70} />
            <KpiCard label="القضايا" value={data.legal.cases_total} icon="cases" delay={140} />
            <KpiCard label="إجازات معلّقة" value={data.hr.leave_pending} icon="leave" delay={210} />
          </div>

          <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <SectionCard title="توزيع المستخدمين">
              <DistRow label="نشط" value={data.users.active} tone="green" />
              <DistRow label="موقوف" value={data.users.suspended} tone="amber" />
              <DistRow label="مقفل" value={data.users.locked} tone="slate" />
            </SectionCard>

            <SectionCard title="توزيع الموظفين">
              <DistRow label="نشط" value={data.employees.active} tone="green" />
              <DistRow label="في إجازة" value={data.employees.on_leave} tone="amber" />
              <DistRow label="موقوف" value={data.employees.suspended} tone="navy" />
              <DistRow label="منتهٍ" value={data.employees.terminated} tone="slate" />
            </SectionCard>

            <SectionCard title="تنبيهات">
              <DistRow label="مستخدمون بلا أدوار" value={data.users.without_roles} tone={data.users.without_roles > 0 ? 'amber' : 'slate'} />
              <DistRow label="إجازات بانتظار القرار" value={data.hr.leave_pending} tone={data.hr.leave_pending > 0 ? 'amber' : 'slate'} />
              <DistRow label="جلسات قادمة" value={data.legal.hearings_upcoming} tone="navy" />
            </SectionCard>
          </div>

          <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <SectionCard title="النظام (RBAC والقضايا)">
              <DistRow label="الأدوار" value={data.rbac.roles} tone="navy" />
              <DistRow label="الصلاحيات" value={data.rbac.permissions} tone="navy" />
              <DistRow label="قضايا مفتوحة" value={data.legal.cases_open} tone="green" />
              <DistRow label="قضايا مغلقة" value={data.legal.cases_closed} tone="slate" />
            </SectionCard>

            <div className="lg:col-span-2">
              <SectionCard title="آخر نشاط النظام">
                {data.activity.length === 0 ? (
                  <p className="py-2 text-sm text-slate-400">لا يوجد نشاط بعد.</p>
                ) : (
                  <ul className="divide-y divide-slate-100">
                    {data.activity.map((a) => (
                      <li key={a.id} className="flex items-center justify-between gap-2 py-2 text-sm">
                        <span className="font-medium text-slate-700">{activityLabel(a.action)}</span>
                        <span className="tabular-nums text-xs text-slate-400">
                          {a.created_at ? new Date(a.created_at).toLocaleString('ar') : '—'}
                        </span>
                      </li>
                    ))}
                  </ul>
                )}
              </SectionCard>
            </div>
          </div>
        </>
      )}
    </div>
  )
}
