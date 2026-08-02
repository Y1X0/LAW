import { useQuery } from '@tanstack/react-query'
import { Badge } from '@/core/ui/primitives'
import { fetchDocuments } from '@/lawyer/api/caseFile'
import { AsyncTab, DataTable } from './AsyncTab'

/** التبويب 5 — مستندات القضية (metadata فقط — لا تحميل فعلي). */
export function DocumentsTab({ caseId }: { caseId: string }) {
  const q = useQuery({ queryKey: ['case', caseId, 'documents'], queryFn: () => fetchDocuments(caseId) })

  return (
    <AsyncTab
      q={q}
      emptyMessage="لا توجد مستندات مفهرسة لهذه القضية."
      render={(docs) => (
        <div className="space-y-3">
          <p className="text-xs text-slate-400">فهرسة (metadata) فقط — لا تحميل ملفات في هذه المرحلة.</p>
          <DataTable head={['العنوان', 'النوع', 'الوصف']}>
            {docs.map((d) => (
              <tr key={d.id} className="border-b border-slate-100 last:border-0">
                <td data-label="العنوان" className="px-4 py-3 font-medium text-slate-800">{d.title}</td>
                <td data-label="النوع" className="px-4 py-3">
                  {d.document_type ? <Badge tone="slate">{d.document_type}</Badge> : '—'}
                </td>
                <td data-label="الوصف" className="px-4 py-3 text-slate-600">{d.description ?? '—'}</td>
              </tr>
            ))}
          </DataTable>
        </div>
      )}
    />
  )
}
