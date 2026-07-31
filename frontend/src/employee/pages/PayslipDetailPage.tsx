import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { printPayslip, usePayslip, type PayslipDetail } from '@/employee/api/payslips'
import { ApiError } from '@/core/api/types'
import { Button, Card } from '@/core/ui/primitives'
import { InfoRow, SectionCard, Stat } from '@/core/ui/section'
import { ErrorState, Skeleton } from '@/core/ui/states'
import { formatCurrency, formatPeriod, payrollStatusLabel } from '@/core/lib/format'

export function PayslipDetailPage() {
  const params = useParams<{ id: string }>()
  const id = Number(params.id)
  const { data, isPending, isError, error } = usePayslip(id)

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold">كشف الراتب</h1>
        <Link to="/payslips" className="text-sm text-brand-600 hover:underline">
          ← كشوفي
        </Link>
      </div>

      {isPending ? (
        <DetailSkeleton />
      ) : isError ? (
        <ErrorState error={error} />
      ) : (
        <PayslipView id={id} slip={data} />
      )}
    </div>
  )
}

function PayslipView({ id, slip }: { id: number; slip: PayslipDetail }) {
  const [printing, setPrinting] = useState(false)
  const [printError, setPrintError] = useState<string | null>(null)

  async function onPrint() {
    setPrinting(true)
    setPrintError(null)
    try {
      await printPayslip(id)
    } catch (e) {
      setPrintError(e instanceof ApiError ? e.message : 'تعذّرت الطباعة.')
    } finally {
      setPrinting(false)
    }
  }

  return (
    <div className="space-y-4">
      <Card>
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <div className="text-lg font-semibold text-slate-800">{slip.employee.name ?? '—'}</div>
            <div className="text-sm text-slate-500">
              {slip.employee.employee_number ?? '—'} · فترة{' '}
              {slip.period.year && slip.period.month ? formatPeriod(slip.period.year, slip.period.month) : '—'} ·{' '}
              {payrollStatusLabel(slip.status)}
            </div>
          </div>
          <Button onClick={onPrint} disabled={printing}>
            {printing ? 'جارٍ التحضير…' : 'طباعة'}
          </Button>
        </div>
        {printError ? <p role="alert" className="mt-2 text-sm text-red-600">{printError}</p> : null}
      </Card>

      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <SectionCard title="المستحقات">
          <LineList lines={slip.earnings} currency={slip.currency} />
          <InfoRow label="الإجمالي" value={<strong>{formatCurrency(slip.gross, slip.currency)}</strong>} />
        </SectionCard>

        <SectionCard title="الخصومات">
          <LineList lines={slip.deductions} currency={slip.currency} emptyLabel="لا خصومات" />
          <InfoRow label="إجمالي الخصومات" value={<strong>{formatCurrency(slip.deductions_total, slip.currency)}</strong>} />
        </SectionCard>
      </div>

      <Card className="bg-brand-50">
        <Stat label="صافي الراتب" value={formatCurrency(slip.net, slip.currency)} />
      </Card>
    </div>
  )
}

function LineList({
  lines,
  currency,
  emptyLabel = 'لا بنود',
}: {
  lines: PayslipDetail['earnings']
  currency: string
  emptyLabel?: string
}) {
  if (lines.length === 0) {
    return <p className="py-3 text-center text-sm text-slate-400">{emptyLabel}</p>
  }
  return (
    <>
      {lines.map((line, i) => (
        <InfoRow key={i} label={line.name} value={formatCurrency(line.amount, currency)} />
      ))}
    </>
  )
}

function DetailSkeleton() {
  return (
    <div className="space-y-4" data-testid="payslip-detail-skeleton">
      <Card>
        <Skeleton className="mb-2 h-5 w-40" />
        <Skeleton className="h-4 w-64" />
      </Card>
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        {Array.from({ length: 2 }).map((_, i) => (
          <Card key={i} className="h-40">
            <Skeleton className="mb-3 h-4 w-24" />
            <Skeleton className="mb-2 h-3 w-full" />
            <Skeleton className="h-3 w-2/3" />
          </Card>
        ))}
      </div>
    </div>
  )
}
