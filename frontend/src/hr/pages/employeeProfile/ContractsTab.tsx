import { useQuery } from '@tanstack/react-query'
import { Badge } from '@/core/ui/primitives'
import { formatDate } from '@/core/lib/format'
import { AsyncPanel } from '@/hr/components/AsyncPanel'
import { HrDataTable } from '@/hr/components/HrDataTable'
import { fetchEmployeeContracts } from '@/hr/api/employeeProfile'

function tone(status: string | null | undefined): 'green' | 'amber' | 'slate' | 'navy' {
  if (status === 'active') return 'green'
  if (status === 'expired') return 'slate'
  return 'navy'
}

/** تبويب العقود — GET /employees/{id}/contracts (قراءة فقط). */
export function ContractsTab({ employeeId }: { employeeId: string }) {
  const q = useQuery({
    queryKey: ['hr', 'employee', employeeId, 'contracts'],
    queryFn: () => fetchEmployeeContracts(employeeId),
  })

  return (
    <AsyncPanel q={q} isEmpty={(d) => d.length === 0} emptyMessage="لا توجد عقود لهذا الموظف.">
      {(contracts) => (
        <HrDataTable head={['النوع', 'من', 'إلى', 'الحالة', 'ملاحظات']}>
          {contracts.map((c) => (
            <tr key={c.id} className="border-b border-slate-100 last:border-0">
              <td className="px-4 py-3 text-slate-700">{c.contract_type ?? '—'}</td>
              <td className="px-4 py-3 tabular-nums text-slate-600">{formatDate(c.start_date ?? null)}</td>
              <td className="px-4 py-3 tabular-nums text-slate-600">{formatDate(c.end_date ?? null)}</td>
              <td className="px-4 py-3">
                {c.status ? <Badge tone={tone(c.status)}>{c.status}</Badge> : '—'}
              </td>
              <td className="px-4 py-3 text-slate-600">{c.notes ?? '—'}</td>
            </tr>
          ))}
        </HrDataTable>
      )}
    </AsyncPanel>
  )
}
