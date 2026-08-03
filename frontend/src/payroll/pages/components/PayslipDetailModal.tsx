import { useQuery } from '@tanstack/react-query'
import { Button } from '@/core/ui/primitives'
import { ErrorState, Skeleton } from '@/core/ui/states'
import { useToast } from '@/core/ui/useToast'
import { Modal } from '@/admin/ui/Modal'
import { formatCurrency, formatPeriod } from '@/core/lib/format'
import { fetchPayslip, printPayslip } from '@/payroll/api/payslips'

/** تفاصيل كشف راتب (Phase 2 / PR-5) — عرض التفصيل المجمّد + طباعة (HTML). */
export function PayslipDetailModal({ itemId, onClose }: { itemId: number; onClose: () => void }) {
  const { show } = useToast()
  const q = useQuery({ queryKey: ['payroll', 'payslip', itemId], queryFn: () => fetchPayslip(itemId) })

  async function onPrint() {
    try {
      await printPayslip(itemId)
    } catch (e) {
      show(e instanceof Error ? e.message : 'تعذّرت الطباعة', 'error')
    }
  }

  return (
    <Modal title="كشف الراتب" onClose={onClose}>
      {q.isPending ? (
        <Skeleton className="h-48 w-full" />
      ) : q.isError ? (
        <ErrorState error={q.error} />
      ) : (
        <div className="space-y-4 text-sm">
          <div className="flex items-center justify-between">
            <div>
              <div className="font-bold text-slate-800">{q.data.employee?.name ?? '—'}</div>
              <div className="tabular-nums text-xs text-slate-400">{q.data.employee?.employee_number ?? ''}</div>
            </div>
            <div className="text-left text-xs text-slate-500">
              {q.data.period?.year ? formatPeriod(q.data.period.year, q.data.period.month ?? 0) : ''}
            </div>
          </div>

          <LineGroup title="الإضافات" lines={q.data.earnings} currency={q.data.currency} />
          <LineGroup title="الاستقطاعات" lines={q.data.deductions} currency={q.data.currency} />

          <div className="space-y-1 border-t border-slate-200 pt-2">
            <Total label="الإجمالي" value={formatCurrency(q.data.gross, q.data.currency)} />
            <Total label="إجمالي الاستقطاعات" value={formatCurrency(q.data.deductions_total, q.data.currency)} />
            <Total label="الصافي" value={formatCurrency(q.data.net, q.data.currency)} strong />
          </div>

          <div className="flex justify-end gap-2 pt-1">
            <Button variant="ghost" onClick={onClose}>إغلاق</Button>
            <Button onClick={() => void onPrint()}>طباعة</Button>
          </div>
        </div>
      )}
    </Modal>
  )
}

function LineGroup({ title, lines, currency }: { title: string; lines: { name: string; amount: number }[]; currency: string }) {
  if (lines.length === 0) return null
  return (
    <div>
      <div className="mb-1 text-xs font-medium text-slate-500">{title}</div>
      <ul className="space-y-1">
        {lines.map((l, i) => (
          <li key={i} className="flex justify-between gap-3">
            <span className="text-slate-700">{l.name}</span>
            <span className="tabular-nums text-slate-800">{formatCurrency(l.amount, currency)}</span>
          </li>
        ))}
      </ul>
    </div>
  )
}

function Total({ label, value, strong = false }: { label: string; value: string; strong?: boolean }) {
  return (
    <div className={`flex justify-between gap-3 ${strong ? 'text-base font-bold text-brand-700' : 'text-slate-600'}`}>
      <span>{label}</span>
      <span className="tabular-nums">{value}</span>
    </div>
  )
}
