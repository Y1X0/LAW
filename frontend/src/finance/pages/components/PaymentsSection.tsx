import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ApiError } from '@/core/api/types'
import { formatCurrency, formatDate } from '@/core/lib/format'
import { Badge, Button, Card } from '@/core/ui/primitives'
import { SectionCard } from '@/core/ui/section'
import { EmptyState, ErrorState, Skeleton } from '@/core/ui/states'
import { useToast } from '@/core/ui/useToast'
import { fetchInvoicePayments, type Payment, paymentMethodLabel, reversePayment } from '@/finance/api/payments'
import { PaymentFormModal } from './PaymentFormModal'

/**
 * سندات قبض الفاتورة (Phase 6 · PR-7). المبالغ من الخادم. أزرار التسجيل/العكس تظهر
 * بالصلاحية فقط، والتسجيل متاح للحالة القابلة (sent/partial)؛ الخادم الحكم النهائي.
 */
export function PaymentsSection({
  invoiceId,
  invoiceStatus,
  canRecordPayment,
}: {
  invoiceId: number
  invoiceStatus: string
  canRecordPayment: boolean
}) {
  const qc = useQueryClient()
  const { show } = useToast()
  const [adding, setAdding] = useState(false)

  const query = useQuery({
    queryKey: ['finance', 'invoice-payments', invoiceId],
    queryFn: () => fetchInvoicePayments(invoiceId),
  })

  const reverse = useMutation({
    mutationFn: (paymentId: number) => reversePayment(paymentId),
    onSuccess: () => {
      show('تم عكس السند')
      void qc.invalidateQueries({ queryKey: ['finance', 'invoice-payments', invoiceId] })
      void qc.invalidateQueries({ queryKey: ['finance', 'invoice', invoiceId] })
      void qc.invalidateQueries({ queryKey: ['finance', 'invoices'] })
    },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّر العكس', 'error'),
  })

  const payments = query.data ?? []
  // سند قابل للعكس: موجب ولم يُعكس بعد (لا يشير إليه سند عكسي آخر).
  const reversedIds = new Set(payments.map((p) => p.reversal_of_id).filter((v): v is number => v != null))
  const isReversible = (p: Payment) => p.amount > 0 && !reversedIds.has(p.id)
  const canRecord = canRecordPayment && (invoiceStatus === 'sent' || invoiceStatus === 'partial')

  return (
    <SectionCard
      title="سندات القبض"
      action={canRecord ? <Button onClick={() => setAdding(true)}>تسجيل دفعة</Button> : undefined}
    >
      {query.isPending ? (
        <Skeleton className="h-14 w-full" />
      ) : query.isError ? (
        <ErrorState error={query.error}><div className="mt-3"><Button onClick={() => void query.refetch()}>إعادة المحاولة</Button></div></ErrorState>
      ) : payments.length === 0 ? (
        <EmptyState message="لا توجد سندات قبض." />
      ) : (
        <ul className="space-y-2.5">
          {payments.map((p) => {
            const reversal = p.amount < 0 || p.reversal_of_id != null
            return (
              <li key={p.id}>
                <Card className="flex flex-col gap-2 p-3.5 sm:flex-row sm:items-center sm:justify-between">
                  <div className="min-w-0">
                    <span className="font-medium text-slate-800">{p.receipt_no ?? `#${p.id}`}</span>
                    <div className="mt-0.5 text-xs text-slate-500">
                      {formatDate(p.payment_date ?? null)} · {paymentMethodLabel(p.method)}
                      {reversal && <Badge tone="slate">عكس</Badge>}
                    </div>
                  </div>
                  <span className="flex flex-shrink-0 items-center gap-3">
                    <span className="tabular-nums text-sm font-medium text-slate-700">{formatCurrency(p.amount, 'SAR')}</span>
                    {canRecordPayment && isReversible(p) && (
                      <Button variant="ghost" onClick={() => reverse.mutate(p.id)} disabled={reverse.isPending}>عكس</Button>
                    )}
                  </span>
                </Card>
              </li>
            )
          })}
        </ul>
      )}

      {adding && <PaymentFormModal invoiceId={invoiceId} onClose={() => setAdding(false)} />}
    </SectionCard>
  )
}
