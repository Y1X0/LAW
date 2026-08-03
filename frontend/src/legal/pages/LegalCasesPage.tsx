import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Badge, Button, Card, Field, SelectField } from '@/core/ui/primitives'
import { PageHeader } from '@/core/ui/section'
import { EmptyState, ErrorState, Skeleton } from '@/core/ui/states'
import { CASE_STATUSES, caseStatusLabel, caseStatusTone, fetchCases } from '@/legal/api/cases'

/**
 * قائمة القضايا للإدارة القانونية (Phase 3 / PR-1) — عرض كل القضايا (view_all)
 * من `GET /cases` الموجود، مع فلاتر بحث/حالة وترقيم. قراءة فقط في هذه الشريحة؛
 * الإنشاء/التعديل/الإغلاق يأتي في PR-2. صفر تغيير خلفي.
 */
export function LegalCasesPage() {
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)

  const query = useQuery({
    queryKey: ['legal', 'cases', { search, status, page }],
    queryFn: () => fetchCases({ search: search.trim() || undefined, status: status || undefined, page }),
  })

  const onSearch = (e: { target: { value: string } }) => { setSearch(e.target.value); setPage(1) }
  const onStatus = (e: { target: { value: string } }) => { setStatus(e.target.value); setPage(1) }

  return (
    <div className="space-y-5">
      <PageHeader title="القضايا" subtitle="كل قضايا المكتب" />

      <Card className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <Field label="بحث" value={search} onChange={onSearch} placeholder="رقم داخلي · عنوان · رقم محكمة" />
        <SelectField label="الحالة" value={status} onChange={onStatus}>
          <option value="">كل الحالات</option>
          {CASE_STATUSES.map((s) => <option key={s} value={s}>{caseStatusLabel(s)}</option>)}
        </SelectField>
      </Card>

      {query.isPending ? (
        <div className="space-y-2.5" data-testid="cases-skeleton">
          {Array.from({ length: 4 }).map((_, i) => (
            <Card key={i}><Skeleton className="h-5 w-48" /><Skeleton className="mt-2 h-4 w-32" /></Card>
          ))}
        </div>
      ) : query.isError ? (
        <ErrorState error={query.error}><div className="mt-3"><Button onClick={() => void query.refetch()}>إعادة المحاولة</Button></div></ErrorState>
      ) : query.data.items.length === 0 ? (
        <EmptyState message="لا توجد قضايا مطابقة." />
      ) : (
        <>
          <ul className="space-y-2.5" aria-busy={query.isFetching}>
            {query.data.items.map((c) => (
              <li key={c.id}>
                <Card className="flex flex-col gap-3 p-3.5 sm:flex-row sm:items-center sm:justify-between">
                  <div className="min-w-0">
                    <div className="font-bold text-slate-800">{c.title}</div>
                    <div className="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-slate-500">
                      <span className="tabular-nums text-slate-400">{c.internal_number}</span>
                      <span>{c.client?.name ?? '—'}</span>
                      {c.responsibleLawyer && <span className="text-slate-400">{c.responsibleLawyer.full_name_ar}</span>}
                    </div>
                  </div>
                  <div className="flex flex-wrap items-center gap-2 text-sm text-slate-600">
                    <span className="tabular-nums">التقدّم: {c.progress}%</span>
                    <Badge tone={caseStatusTone(c.status)}>{caseStatusLabel(c.status)}</Badge>
                  </div>
                </Card>
              </li>
            ))}
          </ul>

          {query.data.meta.total_pages > 1 && (
            <div className="flex items-center justify-between gap-3 text-sm text-slate-600">
              <Button variant="ghost" onClick={() => setPage((p) => Math.max(1, p - 1))} disabled={query.data.meta.page <= 1}>السابق</Button>
              <span className="tabular-nums">صفحة {query.data.meta.page} من {query.data.meta.total_pages}</span>
              <Button variant="ghost" onClick={() => setPage((p) => p + 1)} disabled={query.data.meta.page >= query.data.meta.total_pages}>التالي</Button>
            </div>
          )}
        </>
      )}
    </div>
  )
}
