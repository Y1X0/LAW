import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { Badge, Button, Card, SelectField } from '@/core/ui/primitives'
import { PageHeader } from '@/core/ui/section'
import { EmptyState, ErrorState, Skeleton } from '@/core/ui/states'
import { fetchBranches } from '@/core/api/org'
import {
  MONTHS,
  PERIOD_STATUSES,
  fetchPeriods,
  monthLabel,
  periodStatusLabel,
  periodStatusTone,
} from '@/payroll/api/payroll'
import { CreatePeriodModal } from './components/CreatePeriodModal'

const CURRENT_YEAR = new Date().getFullYear()
const YEARS = Array.from({ length: 6 }, (_, i) => CURRENT_YEAR - 3 + i)

/**
 * قائمة فترات الرواتب (Phase 2 / PR-2) — قراءة/إنشاء من نقاط النهاية الموجودة فقط.
 * فلاتر (سنة/شهر/فرع/حالة) + إنشاء فترة. لا أزرار غير مدعومة خلفياً
 * (إغلاق/إعادة فتح/أرشفة/تعديل) — موثّقة في docs/BACKLOG.md حتى يتوفّر الـBackend.
 */
export function PayrollPeriodsPage() {
  const [year, setYear] = useState('')
  const [month, setMonth] = useState('')
  const [status, setStatus] = useState('')
  const [branchId, setBranchId] = useState('')
  const [page, setPage] = useState(1)
  const [creating, setCreating] = useState(false)

  const branches = useQuery({ queryKey: ['org', 'branches'], queryFn: fetchBranches })
  const query = useQuery({
    queryKey: ['payroll', 'periods', { year, month, status, branchId, page }],
    queryFn: () =>
      fetchPeriods({
        year: year ? Number(year) : undefined,
        month: month ? Number(month) : undefined,
        status: status || undefined,
        branchId: branchId ? Number(branchId) : undefined,
        page,
      }),
  })

  // أي تغيير فلتر يعيد للصفحة الأولى.
  const onFilter = (set: (v: string) => void) => (e: { target: { value: string } }) => { set(e.target.value); setPage(1) }

  return (
    <div className="space-y-5">
      <PageHeader
        title="فترات الرواتب"
        subtitle="إدارة فترات احتساب الرواتب الشهرية"
        action={
          <div className="flex items-center gap-2">
            <Link to="/payroll" className="lp-press rounded-lg px-3 py-2 text-sm font-medium text-brand-700 hover:bg-brand-50">اللوحة</Link>
            <Button onClick={() => setCreating(true)}>إنشاء فترة</Button>
          </div>
        }
      />

      <Card className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <SelectField label="السنة" value={year} onChange={onFilter(setYear)}>
          <option value="">كل السنوات</option>
          {YEARS.map((y) => <option key={y} value={y}>{y}</option>)}
        </SelectField>
        <SelectField label="الشهر" value={month} onChange={onFilter(setMonth)}>
          <option value="">كل الأشهر</option>
          {MONTHS.map((label, i) => <option key={i + 1} value={i + 1}>{label}</option>)}
        </SelectField>
        <SelectField label="الفرع" value={branchId} onChange={onFilter(setBranchId)}>
          <option value="">كل الفروع</option>
          {branches.data?.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
        </SelectField>
        <SelectField label="الحالة" value={status} onChange={onFilter(setStatus)}>
          <option value="">كل الحالات</option>
          {PERIOD_STATUSES.map((s) => <option key={s} value={s}>{periodStatusLabel(s)}</option>)}
        </SelectField>
      </Card>

      {query.isPending ? (
        <div className="space-y-2.5" data-testid="periods-skeleton">
          {Array.from({ length: 4 }).map((_, i) => (
            <Card key={i}><Skeleton className="h-5 w-32" /><Skeleton className="mt-2 h-4 w-24" /></Card>
          ))}
        </div>
      ) : query.isError ? (
        <ErrorState error={query.error}>
          <div className="mt-3"><Button onClick={() => void query.refetch()}>إعادة المحاولة</Button></div>
        </ErrorState>
      ) : query.data.items.length === 0 ? (
        <EmptyState message="لا توجد فترات مطابقة. أنشئ فترة جديدة للبدء." />
      ) : (
        <>
          <ul className="space-y-2.5" aria-busy={query.isFetching}>
            {query.data.items.map((p) => (
              <li key={p.id}>
                <Card className="flex flex-col gap-3 p-3.5 sm:flex-row sm:items-center sm:justify-between">
                  <div className="min-w-0">
                    <div className="font-bold text-brand-700 tabular-nums">{monthLabel(p.month)} {p.year}</div>
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

          <Pagination
            page={query.data.meta.page}
            totalPages={query.data.meta.total_pages}
            onPrev={() => setPage((p) => Math.max(1, p - 1))}
            onNext={() => setPage((p) => p + 1)}
          />
        </>
      )}

      {creating && <CreatePeriodModal onClose={() => setCreating(false)} />}
    </div>
  )
}

function Pagination({ page, totalPages, onPrev, onNext }: { page: number; totalPages: number; onPrev: () => void; onNext: () => void }) {
  if (totalPages <= 1) return null
  return (
    <div className="flex items-center justify-between gap-3 text-sm text-slate-600">
      <Button variant="ghost" onClick={onPrev} disabled={page <= 1}>السابق</Button>
      <span className="tabular-nums">صفحة {page} من {totalPages}</span>
      <Button variant="ghost" onClick={onNext} disabled={page >= totalPages}>التالي</Button>
    </div>
  )
}
