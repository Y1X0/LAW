import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { Button, Card } from '@/core/ui/primitives'
import { InfoRow, PageHeader, SectionCard, Stat } from '@/core/ui/section'
import { EmptyState, ErrorState, Skeleton } from '@/core/ui/states'
import { formatDate, formatDateTime } from '@/core/lib/format'
import { fetchLegalSummary, hearingStatusLabel } from '@/lawyer/api/dashboard'

/**
 * لوحة المحامي (LP-2) — أول شاشة بيانات حقيقية.
 * المصدر الوحيد: GET /api/me/legal-summary (قراءة فقط، مُنطَّق على القضايا المسندة).
 */
export function LawyerDashboardPage() {
  const { data, isPending, isError, error, refetch } = useQuery({
    queryKey: ['me', 'legal-summary'],
    queryFn: fetchLegalSummary,
  })

  return (
    <div className="space-y-5">
      <PageHeader title="الرئيسية" subtitle="لوحة المحامي" />

      {isPending ? (
        <DashboardSkeleton />
      ) : isError ? (
        <ErrorState error={error}>
          <div className="mt-3">
            <Button onClick={() => void refetch()}>إعادة المحاولة</Button>
          </div>
        </ErrorState>
      ) : (
        <DashboardContent data={data} />
      )}
    </div>
  )
}

function DashboardContent({ data }: { data: Awaited<ReturnType<typeof fetchLegalSummary>> }) {
  const { cases, tasks, next_hearing, recent_events, last_worklog } = data

  return (
    <div className="space-y-5">
      {cases.total === 0 ? (
        <Card className="text-center text-sm text-slate-500">لا توجد قضايا مسندة إليك بعد.</Card>
      ) : null}

      {/* 1) البطاقات الرئيسية */}
      <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
        <Card>
          <Stat label="إجمالي القضايا" value={cases.total} />
        </Card>
        <Card>
          <Stat label="القضايا المفتوحة" value={cases.open} />
        </Card>
        <Card>
          <Stat label="القضايا المعلّقة" value={cases.pending} />
        </Card>
        <Card>
          <Stat label="المهام المعلّقة" value={tasks.pending} />
        </Card>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        {/* 2) قسم العمل القريب — أقرب جلسة قادمة */}
        <SectionCard title="أقرب جلسة قادمة">
          {next_hearing ? (
            <div className="space-y-0.5">
              <InfoRow label="رقم القضية" value={next_hearing.case?.internal_number ?? '—'} />
              <InfoRow label="العنوان" value={next_hearing.case?.title ?? '—'} />
              <InfoRow
                label="التاريخ والوقت"
                value={<span className="tabular-nums">{formatDateTime(next_hearing.scheduled_at)}</span>}
              />
              {next_hearing.type ? <InfoRow label="النوع" value={next_hearing.type} /> : null}
              {next_hearing.location ? <InfoRow label="المكان" value={next_hearing.location} /> : null}
              <InfoRow label="الحالة" value={hearingStatusLabel(next_hearing.status)} />
            </div>
          ) : (
            <EmptyState message="لا توجد جلسات قادمة." />
          )}
        </SectionCard>

        {/* 4) الإنجاز اليومي */}
        <SectionCard
          title="آخر إنجاز يومي"
          action={
            <Link to="/worklog" className="text-sm font-medium text-brand-700 hover:underline">
              الانتقال للإنجازات
            </Link>
          }
        >
          {last_worklog ? (
            <div className="space-y-2">
              <InfoRow
                label="التاريخ"
                value={<span className="tabular-nums">{formatDate(last_worklog.work_date)}</span>}
              />
              <p className="whitespace-pre-line text-sm text-slate-700">
                {last_worklog.done_today ?? '—'}
              </p>
            </div>
          ) : (
            <EmptyState message="لم تسجّل إنجازاً بعد." />
          )}
        </SectionCard>
      </div>

      {/* 3) النشاط الأخير */}
      <SectionCard title="النشاط الأخير">
        {recent_events.length > 0 ? (
          <ul className="divide-y divide-slate-100">
            {recent_events.map((ev) => (
              <li key={ev.id} className="flex items-center justify-between gap-3 py-2 text-sm">
                <span className="text-slate-800">{ev.title}</span>
                <span className="shrink-0 tabular-nums text-slate-500">{formatDate(ev.event_date)}</span>
              </li>
            ))}
          </ul>
        ) : (
          <EmptyState message="لا يوجد نشاط حديث." />
        )}
      </SectionCard>
    </div>
  )
}

/** هيكل تحميل للّوحة (Skeleton). */
function DashboardSkeleton() {
  return (
    <div className="space-y-5" data-testid="lawyer-dashboard-skeleton">
      <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
        {Array.from({ length: 4 }).map((_, i) => (
          <Card key={i}>
            <Skeleton className="h-8 w-16" />
            <Skeleton className="mt-2 h-4 w-24" />
          </Card>
        ))}
      </div>
      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <Skeleton className="h-5 w-32" />
          <Skeleton className="mt-3 h-20 w-full" />
        </Card>
        <Card>
          <Skeleton className="h-5 w-32" />
          <Skeleton className="mt-3 h-20 w-full" />
        </Card>
      </div>
      <Card>
        <Skeleton className="h-5 w-32" />
        <Skeleton className="mt-3 h-24 w-full" />
      </Card>
    </div>
  )
}
