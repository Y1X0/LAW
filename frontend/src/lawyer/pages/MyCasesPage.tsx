import { useState, type FormEvent } from 'react'
import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { Badge, Button, Card, Field, SelectField } from '@/core/ui/primitives'
import { PageHeader } from '@/core/ui/section'
import { EmptyState, ErrorState, Skeleton } from '@/core/ui/states'
import { formatDate } from '@/core/lib/format'
import {
  CASE_STATUSES,
  caseStatusLabel,
  fetchCases,
  type CaseListItem,
} from '@/lawyer/api/cases'

const PER_PAGE = 15

function statusTone(status: string): 'green' | 'amber' | 'slate' | 'navy' {
  if (status === 'open') return 'green'
  if (status === 'pending') return 'amber'
  if (status === 'closed') return 'slate'
  return 'navy'
}

/**
 * قائمة قضايا المحامي (LP-3) — المصدر الوحيد GET /api/cases (بعزل view_own).
 * قائمة + بحث + فلتر حالة + ترقيم خادمي. لا تفاصيل قضية هنا (LP-4).
 */
export function MyCasesPage() {
  const [searchInput, setSearchInput] = useState('')
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)

  const { data, isPending, isError, error, refetch, isFetching } = useQuery({
    queryKey: ['cases', { search, status, page }],
    queryFn: () => fetchCases({ search, status, page, perPage: PER_PAGE }),
    placeholderData: keepPreviousData,
  })

  function onSearch(e: FormEvent) {
    e.preventDefault()
    setPage(1)
    setSearch(searchInput.trim())
  }

  function onStatusChange(value: string) {
    setPage(1)
    setStatus(value)
  }

  const hasFilters = search !== '' || status !== ''

  return (
    <div className="space-y-5">
      <PageHeader title="قضاياي" subtitle="القضايا المسندة إليك" />

      {/* شريط البحث والفلترة */}
      <Card>
        <div className="flex flex-col gap-3 md:flex-row md:items-end">
          <form onSubmit={onSearch} className="flex flex-1 items-end gap-2">
            <div className="flex-1">
              <Field
                label="بحث (رقم أو عنوان القضية)"
                value={searchInput}
                onChange={(e) => setSearchInput(e.target.value)}
                placeholder="رقم القضية أو العنوان…"
              />
            </div>
            <Button type="submit">بحث</Button>
          </form>
          <div className="md:w-52">
            <SelectField
              label="الحالة"
              value={status}
              onChange={(e) => onStatusChange(e.target.value)}
            >
              <option value="">كل الحالات</option>
              {CASE_STATUSES.map((s) => (
                <option key={s} value={s}>
                  {caseStatusLabel(s)}
                </option>
              ))}
            </SelectField>
          </div>
        </div>
      </Card>

      {isPending ? (
        <CasesSkeleton />
      ) : isError ? (
        <ErrorState error={error}>
          <div className="mt-3">
            <Button onClick={() => void refetch()}>إعادة المحاولة</Button>
          </div>
        </ErrorState>
      ) : data.items.length === 0 ? (
        <Card>
          <EmptyState
            message={hasFilters ? 'لا توجد نتائج مطابقة.' : 'لا توجد قضايا مسندة إليك.'}
          />
        </Card>
      ) : (
        <>
          <Card className="lp-table-wrap p-0">
            <table
              className={`lp-table sm:min-w-[720px] text-right text-sm transition-opacity ${
                isFetching ? 'opacity-60' : ''
              }`}
              aria-busy={isFetching}
            >
              <thead>
                <tr className="text-xs">
                  <th className="px-4 py-3 text-right">رقم القضية</th>
                  <th className="px-4 py-3 text-right">العنوان</th>
                  <th className="px-4 py-3 text-right">العميل</th>
                  <th className="px-4 py-3 text-right">الحالة</th>
                  <th className="px-4 py-3 text-right">التقدّم</th>
                  <th className="px-4 py-3 text-right">تاريخ الفتح</th>
                </tr>
              </thead>
              <tbody>
                {data.items.map((c) => (
                  <CaseRow key={c.id} c={c} />
                ))}
              </tbody>
            </table>
          </Card>

          <Pagination
            page={data.meta.page}
            totalPages={data.meta.total_pages}
            total={data.meta.total}
            busy={isFetching}
            onPrev={() => setPage((p) => Math.max(1, p - 1))}
            onNext={() => setPage((p) => Math.min(data.meta.total_pages, p + 1))}
          />
        </>
      )}
    </div>
  )
}

function CaseRow({ c }: { c: CaseListItem }) {
  return (
    <tr className="border-b border-slate-100 last:border-0">
      <td data-label="رقم القضية" className="px-4 py-3">
        <Link to={`/cases/${c.id}`} className="font-medium text-brand-700 hover:underline tabular-nums">
          {c.internal_number}
        </Link>
      </td>
      <td data-label="العنوان" className="px-4 py-3 text-slate-800">{c.title}</td>
      <td data-label="العميل" className="px-4 py-3 text-slate-600">{c.client?.name ?? '—'}</td>
      <td data-label="الحالة" className="px-4 py-3">
        <Badge tone={statusTone(c.status)}>{caseStatusLabel(c.status)}</Badge>
      </td>
      <td data-label="التقدّم" className="px-4 py-3">
        <div className="flex items-center gap-2">
          <div className="h-1.5 w-20 overflow-hidden rounded-full bg-slate-200">
            <div className="h-full rounded-full bg-brand-500" style={{ width: `${c.progress}%` }} />
          </div>
          <span className="tabular-nums text-xs text-slate-500">{c.progress}%</span>
        </div>
      </td>
      <td data-label="تاريخ الفتح" className="px-4 py-3 tabular-nums text-slate-600">{formatDate(c.opened_date ?? null)}</td>
    </tr>
  )
}

function Pagination({
  page,
  totalPages,
  total,
  busy,
  onPrev,
  onNext,
}: {
  page: number
  totalPages: number
  total: number
  busy: boolean
  onPrev: () => void
  onNext: () => void
}) {
  return (
    <div className="flex items-center justify-between gap-3 text-sm text-slate-600">
      <span className="tabular-nums">
        صفحة {page} من {totalPages} · {total} قضية
      </span>
      <div className="flex gap-2">
        <Button variant="ghost" onClick={onPrev} disabled={page <= 1 || busy}>
          السابق
        </Button>
        <Button variant="ghost" onClick={onNext} disabled={page >= totalPages || busy}>
          التالي
        </Button>
      </div>
    </div>
  )
}

function CasesSkeleton() {
  return (
    <Card>
      <div data-testid="cases-skeleton" className="space-y-3">
        {Array.from({ length: 6 }).map((_, i) => (
          <Skeleton key={i} className="h-9 w-full" />
        ))}
      </div>
    </Card>
  )
}
