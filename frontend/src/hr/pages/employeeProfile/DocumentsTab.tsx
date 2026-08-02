import { useQuery } from '@tanstack/react-query'
import { formatDate } from '@/core/lib/format'
import { AsyncPanel } from '@/hr/components/AsyncPanel'
import { HrDataTable } from '@/hr/components/HrDataTable'
import { docTypeLabel, fetchEmployeeDocuments } from '@/hr/api/employeeProfile'

/** تبويب الوثائق — GET /employees/{id}/documents (بيانات وصفية فقط، قراءة). */
export function DocumentsTab({ employeeId }: { employeeId: string }) {
  const q = useQuery({
    queryKey: ['hr', 'employee', employeeId, 'documents'],
    queryFn: () => fetchEmployeeDocuments(employeeId),
  })

  return (
    <AsyncPanel q={q} isEmpty={(d) => d.length === 0} emptyMessage="لا توجد وثائق لهذا الموظف.">
      {(docs) => (
        <HrDataTable head={['النوع', 'العنوان', 'تاريخ الانتهاء', 'أضيفت في']}>
          {docs.map((d) => (
            <tr key={d.id} className="border-b border-slate-100 last:border-0">
              <td data-label="النوع" className="px-4 py-3 text-slate-600">{docTypeLabel(d.doc_type)}</td>
              <td data-label="العنوان" className="px-4 py-3 font-medium text-slate-800">{d.title}</td>
              <td data-label="تاريخ الانتهاء" className="px-4 py-3 tabular-nums text-slate-600">{formatDate(d.expiry_date ?? null)}</td>
              <td data-label="أضيفت في" className="px-4 py-3 tabular-nums text-slate-600">{formatDate(d.created_at ?? null)}</td>
            </tr>
          ))}
        </HrDataTable>
      )}
    </AsyncPanel>
  )
}
