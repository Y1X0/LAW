import { useQuery } from '@tanstack/react-query'
import { Badge, Card } from '@/core/ui/primitives'
import { formatDate } from '@/core/lib/format'
import { AsyncPanel } from '@/hr/components/AsyncPanel'
import { HrDataTable } from '@/hr/components/HrDataTable'
import { fetchEmployeeLeaves, leaveStatusLabel, leaveStatusTone } from '@/hr/api/employeeProfile'

/** تبويب الإجازات — الأرصدة + آخر الطلبات (قراءة). يتطلّب leaves.view_all (403 → حالة صلاحية). */
export function LeavesTab({ employeeId }: { employeeId: string }) {
  const q = useQuery({
    queryKey: ['hr', 'employee', employeeId, 'leaves'],
    queryFn: () => fetchEmployeeLeaves(employeeId),
  })

  return (
    <AsyncPanel
      q={q}
      isEmpty={(d) => d.balances.length === 0 && d.requests.length === 0}
      emptyMessage="لا توجد بيانات إجازات لهذا الموظف."
    >
      {({ balances, requests }) => (
        <div className="space-y-5">
          {balances.length > 0 ? (
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {balances.map((b) => (
                <Card key={b.id} className="relative overflow-hidden">
                  <span className="absolute inset-x-0 top-0 h-[3px] bg-gradient-to-l from-transparent via-gold-400 to-transparent" />
                  <div className="text-sm font-semibold text-slate-700">{b.leaveType?.name ?? 'إجازة'}</div>
                  <div className="mt-2 text-[28px] font-extrabold leading-none tabular-nums text-brand-700">
                    {b.remaining_days ?? 0}
                  </div>
                  <div className="mt-1 text-xs text-slate-500 tabular-nums">
                    متبقٍّ من {b.entitled_days ?? 0} · مستهلك {b.consumed_days ?? 0}
                  </div>
                </Card>
              ))}
            </div>
          ) : null}

          {requests.length > 0 ? (
            <HrDataTable head={['النوع', 'من', 'إلى', 'الأيام', 'الحالة']}>
              {requests.map((r) => (
                <tr key={r.id} className="border-b border-slate-100 last:border-0">
                  <td className="px-4 py-3 text-slate-700">{r.leaveType?.name ?? '—'}</td>
                  <td className="px-4 py-3 tabular-nums text-slate-600">{formatDate(r.start_date ?? null)}</td>
                  <td className="px-4 py-3 tabular-nums text-slate-600">{formatDate(r.end_date ?? null)}</td>
                  <td className="px-4 py-3 tabular-nums text-slate-600">{r.days ?? '—'}</td>
                  <td className="px-4 py-3">
                    <Badge tone={leaveStatusTone(r.status)}>{leaveStatusLabel(r.status)}</Badge>
                  </td>
                </tr>
              ))}
            </HrDataTable>
          ) : null}
        </div>
      )}
    </AsyncPanel>
  )
}
