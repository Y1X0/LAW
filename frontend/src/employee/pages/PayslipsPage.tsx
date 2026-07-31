import { Link } from 'react-router-dom'
import { usePayslips } from '@/employee/api/payslips'
import { Card } from '@/core/ui/primitives'
import { EmptyState, ErrorState, Skeleton } from '@/core/ui/states'
import { formatCurrency, formatPeriod, payrollStatusLabel } from '@/core/lib/format'

export function PayslipsPage() {
  const { data, isPending, isError, error } = usePayslips()

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold">كشوف راتبي</h1>
      {isPending ? (
        <ListSkeleton />
      ) : isError ? (
        <ErrorState error={error} />
      ) : data.length === 0 ? (
        <EmptyState message="لا توجد كشوف رواتب معتمدة بعد." />
      ) : (
        <ul className="space-y-3">
          {data.map((p) => (
            <li key={p.payroll_item_id}>
              <Link to={`/payslips/${p.payroll_item_id}`} className="block">
                <Card className="transition hover:border-brand-300 hover:shadow">
                  <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                      <div className="text-sm font-semibold text-slate-800">
                        فترة {formatPeriod(p.year, p.month)}
                      </div>
                      <span className="mt-1 inline-block rounded-full bg-brand-50 px-2 py-0.5 text-xs text-brand-700">
                        {payrollStatusLabel(p.status)}
                      </span>
                    </div>
                    <div className="text-left">
                      <div className="text-lg font-bold text-brand-700">{formatCurrency(p.net, p.currency)}</div>
                      <div className="text-xs text-slate-400">صافي الراتب</div>
                    </div>
                  </div>
                </Card>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}

function ListSkeleton() {
  return (
    <ul className="space-y-3" data-testid="payslips-skeleton">
      {Array.from({ length: 3 }).map((_, i) => (
        <li key={i}>
          <Card>
            <div className="flex items-center justify-between">
              <Skeleton className="h-5 w-32" />
              <Skeleton className="h-6 w-24" />
            </div>
          </Card>
        </li>
      ))}
    </ul>
  )
}
