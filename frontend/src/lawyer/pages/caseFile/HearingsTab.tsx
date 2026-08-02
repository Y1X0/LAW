import { useQuery } from '@tanstack/react-query'
import { Badge } from '@/core/ui/primitives'
import { formatDateTime } from '@/core/lib/format'
import { hearingStatusLabel } from '@/lawyer/api/dashboard'
import { fetchHearings } from '@/lawyer/api/caseFile'
import { AsyncTab, DataTable } from './AsyncTab'

function tone(status: string): 'green' | 'amber' | 'slate' | 'navy' {
  if (status === 'scheduled') return 'navy'
  if (status === 'held') return 'green'
  if (status === 'postponed') return 'amber'
  return 'slate'
}

/** التبويب 3 — جلسات القضية. */
export function HearingsTab({ caseId }: { caseId: string }) {
  const q = useQuery({ queryKey: ['case', caseId, 'hearings'], queryFn: () => fetchHearings(caseId) })

  return (
    <AsyncTab
      q={q}
      emptyMessage="لا توجد جلسات لهذه القضية."
      render={(hearings) => (
        <DataTable head={['التاريخ والوقت', 'النوع', 'الحالة', 'المكان', 'النتيجة/الملاحظات']}>
          {hearings.map((h) => (
            <tr key={h.id} className="border-b border-slate-100 last:border-0">
              <td data-label="التاريخ والوقت" className="px-4 py-3 tabular-nums text-slate-700">{formatDateTime(h.scheduled_at)}</td>
              <td data-label="النوع" className="px-4 py-3 text-slate-600">{h.type ?? '—'}</td>
              <td data-label="الحالة" className="px-4 py-3">
                <Badge tone={tone(h.status)}>{hearingStatusLabel(h.status)}</Badge>
              </td>
              <td data-label="المكان" className="px-4 py-3 text-slate-600">{h.location ?? '—'}</td>
              <td data-label="النتيجة/الملاحظات" className="px-4 py-3 text-slate-600">{h.outcome ?? h.postponed_reason ?? h.notes ?? '—'}</td>
            </tr>
          ))}
        </DataTable>
      )}
    />
  )
}
