import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { formatCurrency } from '@/core/lib/format'
import { Badge, Button, Card, Field, SelectField } from '@/core/ui/primitives'
import { PageHeader } from '@/core/ui/section'
import { EmptyState, ErrorState, Skeleton } from '@/core/ui/states'
import { useFinanceCapabilities } from '@/finance/api/capabilities'
import {
  fetchInvoicesPage,
  INVOICE_STATUSES,
  invoiceStatusLabel,
  invoiceStatusTone,
} from '@/finance/api/invoices'
import { InvoiceFormModal } from './components/InvoiceFormModal'
import { Pagination } from './components/Pagination'

/** قائمة الفواتير (Phase 6 · PR-5) — بحث/فلترة بالحالة + ترقيم. */
export function InvoicesListPage() {
  const { canCreate } = useFinanceCapabilities()
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const [creating, setCreating] = useState(false)

  function reset(fn: () => void) {
    fn()
    setPage(1)
  }

  const query = useQuery({
    queryKey: ['finance', 'invoices', { search, status, page }],
    queryFn: () => fetchInvoicesPage({ search: search.trim() || undefined, status: status || undefined, page }),
  })

  return (
    <div className="space-y-5">
      <PageHeader
        title="الفواتير"
        subtitle="فواتير المكتب"
        action={canCreate ? <Button onClick={() => setCreating(true)}>إنشاء فاتورة</Button> : undefined}
      />

      <Card className="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <Field label="بحث (رقم الفاتورة)" value={search} onChange={(e) => reset(() => setSearch(e.target.value))} placeholder="INV-000001" />
        <SelectField label="الحالة" value={status} onChange={(e) => reset(() => setStatus(e.target.value))}>
          <option value="">كل الحالات</option>
          {INVOICE_STATUSES.map((s) => (
            <option key={s} value={s}>{invoiceStatusLabel(s)}</option>
          ))}
        </SelectField>
      </Card>

      {query.isPending ? (
        <Skeleton className="h-24 w-full" />
      ) : query.isError ? (
        <ErrorState error={query.error}>
          <div className="mt-3"><Button onClick={() => void query.refetch()}>إعادة المحاولة</Button></div>
        </ErrorState>
      ) : query.data.items.length === 0 ? (
        <EmptyState message="لا توجد فواتير مطابقة." />
      ) : (
        <>
          <ul className="space-y-2.5" aria-busy={query.isFetching}>
            {query.data.items.map((inv) => (
              <li key={inv.id}>
                <Card className="flex flex-col gap-2 p-3.5 sm:flex-row sm:items-center sm:justify-between">
                  <div className="min-w-0">
                    <Link to={`/finance/invoices/${inv.id}`} className="font-bold text-brand-700 hover:underline">{inv.invoice_no ?? `#${inv.id}`}</Link>
                    <div className="mt-0.5 text-xs text-slate-500">{inv.client?.name ?? '—'}</div>
                  </div>
                  <span className="flex flex-shrink-0 items-center gap-3">
                    <span className="tabular-nums text-sm font-medium text-slate-700">{formatCurrency(inv.total, 'SAR')}</span>
                    <Badge tone={invoiceStatusTone(inv.status)}>{invoiceStatusLabel(inv.status)}</Badge>
                  </span>
                </Card>
              </li>
            ))}
          </ul>
          <Pagination page={query.data.meta.page} totalPages={query.data.meta.total_pages} onChange={setPage} />
        </>
      )}

      {creating && <InvoiceFormModal onClose={() => setCreating(false)} />}
    </div>
  )
}
