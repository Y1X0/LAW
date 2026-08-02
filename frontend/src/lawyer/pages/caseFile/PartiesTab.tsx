import { useQuery } from '@tanstack/react-query'
import { Badge } from '@/core/ui/primitives'
import { fetchParties, partyTypeLabel } from '@/lawyer/api/caseFile'
import { AsyncTab, DataTable } from './AsyncTab'

/** التبويب 2 — أطراف القضية. */
export function PartiesTab({ caseId }: { caseId: string }) {
  const q = useQuery({ queryKey: ['case', caseId, 'parties'], queryFn: () => fetchParties(caseId) })

  return (
    <AsyncTab
      q={q}
      emptyMessage="لا يوجد أطراف مسجّلون لهذه القضية."
      render={(parties) => (
        <DataTable head={['الاسم', 'الصفة', 'الهاتف', 'ملاحظات']}>
          {parties.map((p) => (
            <tr key={p.id} className="border-b border-slate-100 last:border-0">
              <td data-label="الاسم" className="px-4 py-3 font-medium text-slate-800">{p.name}</td>
              <td data-label="الصفة" className="px-4 py-3">
                <Badge tone="navy">{partyTypeLabel(p.type)}</Badge>
              </td>
              <td data-label="الهاتف" className="px-4 py-3 tabular-nums text-slate-600">{p.phone ?? '—'}</td>
              <td data-label="ملاحظات" className="px-4 py-3 text-slate-600">{p.notes ?? '—'}</td>
            </tr>
          ))}
        </DataTable>
      )}
    />
  )
}
