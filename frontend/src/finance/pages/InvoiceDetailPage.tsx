import { useState, type ReactNode } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link, useParams } from 'react-router-dom'
import { ApiError } from '@/core/api/types'
import { formatCurrency, formatDate } from '@/core/lib/format'
import { Badge, Button, Card } from '@/core/ui/primitives'
import { PageHeader, SectionCard } from '@/core/ui/section'
import { ErrorState, Skeleton } from '@/core/ui/states'
import { useToast } from '@/core/ui/useToast'
import { useFinanceCapabilities } from '@/finance/api/capabilities'
import {
  approveInvoice,
  cancelInvoice,
  fetchInvoice,
  type Invoice,
  invoiceStatusLabel,
  invoiceStatusTone,
} from '@/finance/api/invoices'
import { InvoiceFormModal } from './components/InvoiceFormModal'

function Info({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div>
      <div className="text-xs text-slate-500">{label}</div>
      <div className="text-sm text-slate-800">{children}</div>
    </div>
  )
}

function Money({ label, value, strong = false }: { label: string; value: number; strong?: boolean }) {
  return (
    <div className="flex items-center justify-between">
      <span className={strong ? 'font-semibold text-slate-800' : 'text-slate-600'}>{label}</span>
      <span className={`tabular-nums ${strong ? 'font-bold text-brand-700' : 'text-slate-700'}`}>{formatCurrency(value, 'SAR')}</span>
    </div>
  )
}

/** تفاصيل فاتورة (Phase 6 · PR-5) — المبالغ من الخادم؛ الأزرار حسب الصلاحية والحالة. */
export function InvoiceDetailPage() {
  const { id } = useParams()
  const invoiceId = Number(id)
  const qc = useQueryClient()
  const { show } = useToast()
  const { canCreate, canApprove } = useFinanceCapabilities()
  const [editing, setEditing] = useState(false)
  const [confirmingCancel, setConfirmingCancel] = useState(false)

  const query = useQuery({
    queryKey: ['finance', 'invoice', invoiceId],
    queryFn: () => fetchInvoice(invoiceId),
    enabled: Number.isFinite(invoiceId),
  })

  const invalidate = () => {
    void qc.invalidateQueries({ queryKey: ['finance', 'invoice', invoiceId] })
    void qc.invalidateQueries({ queryKey: ['finance', 'invoices'] })
  }

  const approve = useMutation({
    mutationFn: () => approveInvoice(invoiceId),
    onSuccess: () => { show('تم اعتماد الفاتورة وترحيل القيد'); invalidate() },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّر الاعتماد', 'error'),
  })
  const cancel = useMutation({
    mutationFn: () => cancelInvoice(invoiceId),
    onSuccess: () => { show('تم إلغاء الفاتورة'); setConfirmingCancel(false); invalidate() },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّر الإلغاء', 'error'),
  })

  if (query.isPending) return <Card><Skeleton className="h-40 w-full" /></Card>
  if (query.isError) {
    return (
      <ErrorState error={query.error}>
        <div className="mt-3"><Link to="/finance/invoices" className="text-brand-700 hover:underline">← عودة للفواتير</Link></div>
      </ErrorState>
    )
  }

  const invoice: Invoice = query.data
  const isDraft = invoice.status === 'draft'

  return (
    <div className="space-y-5">
      <PageHeader
        title={`فاتورة ${invoice.invoice_no ?? `#${invoice.id}`}`}
        action={
          <span className="flex flex-wrap items-center gap-2">
            <Link to="/finance/invoices" className="text-sm text-brand-700 hover:underline">← الفواتير</Link>
            {isDraft && canCreate && <Button variant="ghost" onClick={() => setEditing(true)}>تعديل</Button>}
            {isDraft && canApprove && <Button onClick={() => approve.mutate()} disabled={approve.isPending}>{approve.isPending ? 'جارٍ…' : 'اعتماد'}</Button>}
            {isDraft && canCreate && (
              confirmingCancel ? (
                <>
                  <Button variant="ghost" onClick={() => cancel.mutate()} disabled={cancel.isPending}>تأكيد الإلغاء</Button>
                  <Button variant="ghost" onClick={() => setConfirmingCancel(false)} disabled={cancel.isPending}>تراجع</Button>
                </>
              ) : (
                <Button variant="ghost" onClick={() => setConfirmingCancel(true)}>إلغاء</Button>
              )
            )}
          </span>
        }
      />

      <Card className="flex flex-wrap items-center gap-x-8 gap-y-3">
        <Info label="الحالة"><Badge tone={invoiceStatusTone(invoice.status)}>{invoiceStatusLabel(invoice.status)}</Badge></Info>
        <Info label="العميل">{invoice.client?.name ?? `#${invoice.client_id}`}</Info>
        <Info label="تاريخ الإصدار">{formatDate(invoice.issue_date ?? null)}</Info>
        <Info label="تاريخ الاستحقاق">{invoice.due_date ? formatDate(invoice.due_date) : '—'}</Info>
      </Card>

      <SectionCard title="البنود">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="text-right text-xs text-slate-500">
                <th className="py-1.5 font-medium">الوصف</th>
                <th className="py-1.5 font-medium">الكمية</th>
                <th className="py-1.5 font-medium">سعر الوحدة</th>
                <th className="py-1.5 font-medium">ضريبة٪</th>
                <th className="py-1.5 font-medium">الإجمالي (قبل الضريبة)</th>
              </tr>
            </thead>
            <tbody>
              {(invoice.items ?? []).map((it) => (
                <tr key={it.id} className="border-t border-slate-100">
                  <td className="py-1.5 text-slate-800">{it.description}</td>
                  <td className="py-1.5 tabular-nums text-slate-700">{it.quantity}</td>
                  <td className="py-1.5 tabular-nums text-slate-700">{formatCurrency(it.unit_price, 'SAR')}</td>
                  <td className="py-1.5 tabular-nums text-slate-700">{it.tax_rate}%</td>
                  <td className="py-1.5 tabular-nums text-slate-700">{formatCurrency(it.line_total, 'SAR')}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </SectionCard>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <SectionCard title="الإجماليات">
          <div className="space-y-1.5">
            <Money label="المجموع قبل الضريبة" value={invoice.subtotal} />
            <Money label="الخصم" value={invoice.discount} />
            <Money label="الضريبة" value={invoice.tax_amount} />
            <Money label="الإجمالي" value={invoice.total} strong />
            <Money label="المسدَّد" value={invoice.paid_amount} />
            <Money label="المتبقّي" value={invoice.balance} />
          </div>
        </SectionCard>

        {invoice.journalEntry && (
          <SectionCard title="القيد المحاسبي الناتج">
            <div className="space-y-1 text-sm">
              <Info label="رقم القيد">{invoice.journalEntry.entry_no ?? '—'}</Info>
              <Info label="التاريخ">{formatDate(invoice.journalEntry.entry_date)}</Info>
              <Info label="الحالة">{invoice.journalEntry.posted ? 'مُرحَّل' : 'غير مُرحَّل'}</Info>
            </div>
          </SectionCard>
        )}
      </div>

      {editing && <InvoiceFormModal existing={invoice} onClose={() => setEditing(false)} />}
    </div>
  )
}
