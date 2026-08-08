import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { formatCurrency, formatDate } from '@/core/lib/format'
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
        <Card className="p-0"><div className="space-y-px p-3">
          {Array.from({ length: 8 }).map((_, i) => (
            <div key={i} className="flex items-center gap-4 px-1 py-2.5"><Skeleton className="h-4 w-24" /><Skeleton className="h-4 flex-1" /><Skeleton className="h-4 w-20" /></div>
          ))}
        </div></Card>
      ) : query.isError ? (
        <ErrorState error={query.error}>
          <div className="mt-3"><Button onClick={() => void query.refetch()}>إعادة المحاولة</Button></div>
        </ErrorState>
      ) : query.data.items.length === 0 ? (
        <EmptyState message="لا توجد فواتير مطابقة." />
      ) : (
        <>
          {/* جدول مؤسسي كثيف — يتحوّل إلى بطاقات مكدّسة على الجوّال عبر .lp-table. */}
          <Card className="overflow-hidden p-0">
            <div className="lp-table-wrap">
              <table className={`lp-table text-right text-sm sm:min-w-[820px] ${query.isFetching ? 'opacity-60 transition-opacity' : ''}`} aria-busy={query.isFetching}>
                <thead>
                  <tr>
                    <th className="px-4 py-2.5 text-right">رقم الفاتورة</th>
                    <th className="px-4 py-2.5 text-right">العميل</th>
                    <th className="px-4 py-2.5 text-right">تاريخ الإصدار</th>
                    <th className="px-4 py-2.5 text-right">الإجمالي</th>
                    <th className="px-4 py-2.5 text-right">المدفوع</th>
                    <th className="px-4 py-2.5 text-right">المتبقّي</th>
                    <th className="px-4 py-2.5 text-right">الحالة</th>
                    <th className="px-4 py-2.5 text-right"><span className="sr-only">إجراءات</span></th>
                  </tr>
                </thead>
                <tbody>
                  {query.data.items.map((inv) => (
                    <tr key={inv.id} className="lp-row">
                      <td data-label="رقم الفاتورة" className="whitespace-nowrap px-4 py-2.5">
                        <Link to={`/finance/invoices/${inv.id}`} className="font-semibold text-brand-700 hover:underline">{inv.invoice_no ?? `#${inv.id}`}</Link>
                      </td>
                      <td data-label="العميل" className="px-4 py-2.5 text-slate-700">{inv.client?.name ?? '—'}</td>
                      <td data-label="تاريخ الإصدار" className="whitespace-nowrap px-4 py-2.5 tabular-nums text-slate-500">{formatDate(inv.issue_date ?? null)}</td>
                      <td data-label="الإجمالي" className="whitespace-nowrap px-4 py-2.5 tabular-nums font-medium text-slate-800">{formatCurrency(inv.total, 'SAR')}</td>
                      <td data-label="المدفوع" className="whitespace-nowrap px-4 py-2.5 tabular-nums text-green-700">{formatCurrency(inv.paid_amount, 'SAR')}</td>
                      <td data-label="المتبقّي" className="whitespace-nowrap px-4 py-2.5 tabular-nums text-slate-700">{formatCurrency(inv.balance, 'SAR')}</td>
                      <td data-label="الحالة" className="px-4 py-2.5"><Badge tone={invoiceStatusTone(inv.status)}>{invoiceStatusLabel(inv.status)}</Badge></td>
                      <td data-label="" className="px-4 py-2.5 text-left">
                        <Link to={`/finance/invoices/${inv.id}`} className="text-xs font-semibold text-brand-600 hover:underline">فتح ←</Link>
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

      {creating && <InvoiceFormModal onClose={() => setCreating(false)} />}
    </div>
  )
}
