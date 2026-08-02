import { useState, type FormEvent } from 'react'
import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { Button, Card, Field } from '@/core/ui/primitives'
import { PageHeader } from '@/core/ui/section'
import { EmptyState, ErrorState, Skeleton } from '@/core/ui/states'
import { activityLabel, fetchAudit } from '@/admin/api/audit'

const PER_PAGE = 20

/**
 * سجلّ التدقيق (ADMIN-5) — عرض `GET /admin/audit` للقراءة فقط مع فلترة بالإجراء وترقيم.
 */
export function AdminAuditPage() {
  const [actionInput, setActionInput] = useState('')
  const [action, setAction] = useState('')
  const [page, setPage] = useState(1)

  const { data, isPending, isError, error, refetch, isFetching } = useQuery({
    queryKey: ['admin', 'audit', { action, page }],
    queryFn: () => fetchAudit({ action, page, perPage: PER_PAGE }),
    placeholderData: keepPreviousData,
  })

  function onSearch(e: FormEvent) {
    e.preventDefault()
    setPage(1)
    setAction(actionInput.trim())
  }

  return (
    <div className="space-y-5">
      <PageHeader title="سجلّ التدقيق" subtitle="أحداث النظام (للقراءة فقط)" />

      <Card>
        <form onSubmit={onSearch} className="flex items-end gap-2">
          <div className="flex-1">
            <Field
              label="تصفية بالإجراء"
              value={actionInput}
              onChange={(e) => setActionInput(e.target.value)}
              placeholder="مثال: login أو user_created"
            />
          </div>
          <Button type="submit">تصفية</Button>
        </form>
      </Card>

      {isPending ? (
        <Card>
          <div data-testid="audit-skeleton" className="space-y-3">
            {Array.from({ length: 8 }).map((_, i) => (
              <Skeleton key={i} className="h-8 w-full" />
            ))}
          </div>
        </Card>
      ) : isError ? (
        <ErrorState error={error}>
          <div className="mt-3">
            <Button onClick={() => void refetch()}>إعادة المحاولة</Button>
          </div>
        </ErrorState>
      ) : data.items.length === 0 ? (
        <Card>
          <EmptyState message={action ? 'لا توجد نتائج مطابقة.' : 'لا يوجد نشاط بعد.'} />
        </Card>
      ) : (
        <>
          <Card className="lp-table-wrap p-0">
            <table
              className={`lp-table sm:min-w-[760px] text-right text-sm transition-opacity ${isFetching ? 'opacity-60' : ''}`}
              aria-busy={isFetching}
            >
              <thead>
                <tr className="text-xs">
                  <th className="px-4 py-3 text-right">الإجراء</th>
                  <th className="px-4 py-3 text-right">المستخدم</th>
                  <th className="px-4 py-3 text-right">الكيان</th>
                  <th className="px-4 py-3 text-right">IP</th>
                  <th className="px-4 py-3 text-right">التاريخ</th>
                </tr>
              </thead>
              <tbody>
                {data.items.map((e) => (
                  <tr key={e.id} className="border-b border-slate-100 last:border-0">
                    <td data-label="الإجراء" className="px-4 py-3 font-medium text-slate-800">{activityLabel(e.action)}</td>
                    <td data-label="المستخدم" className="px-4 py-3 text-slate-600">{e.user?.name ?? '—'}</td>
                    <td data-label="الكيان" className="px-4 py-3 text-slate-500">
                      {e.auditable_type ? `${e.auditable_type}#${e.auditable_id ?? '—'}` : '—'}
                    </td>
                    <td data-label="IP" className="px-4 py-3 tabular-nums text-xs text-slate-400">{e.ip_address ?? '—'}</td>
                    <td data-label="التاريخ" className="px-4 py-3 tabular-nums text-xs text-slate-400">
                      {e.created_at ? new Date(e.created_at).toLocaleString('ar') : '—'}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </Card>

          <div className="flex items-center justify-between gap-3 text-sm text-slate-600">
            <span className="tabular-nums">
              صفحة {data.meta.page} من {data.meta.total_pages} · {data.meta.total} حدث
            </span>
            <div className="flex gap-2">
              <Button variant="ghost" onClick={() => setPage((p) => Math.max(1, p - 1))} disabled={page <= 1 || isFetching}>
                السابق
              </Button>
              <Button
                variant="ghost"
                onClick={() => setPage((p) => Math.min(data.meta.total_pages, p + 1))}
                disabled={page >= data.meta.total_pages || isFetching}
              >
                التالي
              </Button>
            </div>
          </div>
        </>
      )}
    </div>
  )
}
