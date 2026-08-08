import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { Badge, Button, Card, Field, SelectField } from '@/core/ui/primitives'
import { PageHeader } from '@/core/ui/section'
import { EmptyState, ErrorState, Skeleton } from '@/core/ui/states'
import { Pagination } from './components/Pagination'
import { CASE_STATUSES, caseStatusLabel, caseStatusTone, fetchCases } from '@/legal/api/cases'
import { CaseFormModal } from './components/CaseFormModal'

/**
 * قائمة القضايا للإدارة القانونية (Phase 3 / PR-1) — عرض كل القضايا (view_all)
 * من `GET /cases` الموجود، مع فلاتر بحث/حالة وترقيم. قراءة فقط في هذه الشريحة؛
 * الإنشاء/التعديل/الإغلاق يأتي في PR-2. صفر تغيير خلفي.
 */
export function LegalCasesPage() {
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const [creating, setCreating] = useState(false)

  const query = useQuery({
    queryKey: ['legal', 'cases', { search, status, page }],
    queryFn: () => fetchCases({ search: search.trim() || undefined, status: status || undefined, page }),
  })

  const onSearch = (e: { target: { value: string } }) => { setSearch(e.target.value); setPage(1) }
  const onStatus = (e: { target: { value: string } }) => { setStatus(e.target.value); setPage(1) }

  return (
    <div className="space-y-5">
      <PageHeader title="القضايا" subtitle="كل قضايا المكتب" action={<Button onClick={() => setCreating(true)}>إنشاء قضية</Button>} />

      <Card className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <Field label="بحث" value={search} onChange={onSearch} placeholder="رقم داخلي · عنوان · رقم محكمة" />
        <SelectField label="الحالة" value={status} onChange={onStatus}>
          <option value="">كل الحالات</option>
          {CASE_STATUSES.map((s) => <option key={s} value={s}>{caseStatusLabel(s)}</option>)}
        </SelectField>
      </Card>

      {query.isPending ? (
        <Card className="p-0" data-testid="cases-skeleton">
          <div className="space-y-px p-3">
            {Array.from({ length: 8 }).map((_, i) => (
              <div key={i} className="flex items-center gap-4 px-1 py-2.5">
                <Skeleton className="h-4 w-24" /><Skeleton className="h-4 flex-1" /><Skeleton className="h-4 w-16" />
              </div>
            ))}
          </div>
        </Card>
      ) : query.isError ? (
        <ErrorState error={query.error}><div className="mt-3"><Button onClick={() => void query.refetch()}>إعادة المحاولة</Button></div></ErrorState>
      ) : query.data.items.length === 0 ? (
        <EmptyState message="لا توجد قضايا مطابقة." />
      ) : (
        <>
          {/* جدول مؤسسي كثيف — صفوف مُدمجة وخطوط شعرية على سطح المكتب، ويتحوّل إلى
              بطاقات مكدّسة على الجوّال تلقائياً عبر .lp-table (كل خلية بـ data-label). */}
          <Card className="overflow-hidden p-0">
            <div className="lp-table-wrap">
              <table className={`lp-table text-right text-sm sm:min-w-[900px] ${query.isFetching ? 'opacity-60 transition-opacity' : ''}`} aria-busy={query.isFetching}>
                <thead>
                  <tr>
                    <th className="px-4 py-2.5 text-right">الرقم الداخلي</th>
                    <th className="px-4 py-2.5 text-right">القضيّة</th>
                    <th className="px-4 py-2.5 text-right">الموكّل</th>
                    <th className="px-4 py-2.5 text-right">النوع</th>
                    <th className="px-4 py-2.5 text-right">المحامي</th>
                    <th className="px-4 py-2.5 text-right">الحالة</th>
                    <th className="px-4 py-2.5 text-right">التقدّم</th>
                    <th className="px-4 py-2.5 text-right"><span className="sr-only">إجراءات</span></th>
                  </tr>
                </thead>
                <tbody>
                  {query.data.items.map((c) => (
                    <tr key={c.id} className="lp-row">
                      <td data-label="الرقم الداخلي" className="whitespace-nowrap px-4 py-2.5 tabular-nums text-slate-500">{c.internal_number}</td>
                      <td data-label="القضيّة" className="px-4 py-2.5">
                        <Link to={`/legal/cases/${c.id}`} className="font-semibold text-brand-700 hover:underline">{c.title}</Link>
                        {c.court_case_number && <div className="text-xs text-slate-400 tabular-nums">محكمة: {c.court_case_number}</div>}
                      </td>
                      <td data-label="الموكّل" className="px-4 py-2.5 text-slate-700">{c.client?.name ?? '—'}</td>
                      <td data-label="النوع" className="px-4 py-2.5 text-slate-600">{c.case_type ?? '—'}</td>
                      <td data-label="المحامي" className="px-4 py-2.5 text-slate-600">{c.responsibleLawyer?.full_name_ar ?? '—'}</td>
                      <td data-label="الحالة" className="px-4 py-2.5"><Badge tone={caseStatusTone(c.status)}>{caseStatusLabel(c.status)}</Badge></td>
                      <td data-label="التقدّم" className="px-4 py-2.5">
                        <div className="flex items-center gap-2">
                          <div className="h-1.5 w-16 overflow-hidden rounded-full bg-slate-200">
                            <div className="h-full rounded-full bg-brand-600" style={{ width: `${c.progress}%` }} />
                          </div>
                          <span className="w-9 tabular-nums text-xs text-slate-500">{c.progress}%</span>
                        </div>
                      </td>
                      <td data-label="" className="px-4 py-2.5 text-left">
                        <Link to={`/legal/cases/${c.id}`} className="text-xs font-semibold text-brand-600 hover:underline">فتح ←</Link>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </Card>

          <Pagination page={query.data.meta.page} totalPages={query.data.meta.total_pages} onChange={setPage} />
        </>
      )}

      {creating && <CaseFormModal onSaved={() => {}} onClose={() => setCreating(false)} />}
    </div>
  )
}
