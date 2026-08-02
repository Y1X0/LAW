import { useQuery } from '@tanstack/react-query'
import { fetchArchive } from '@/lawyer/api/caseFile'
import { AsyncTab, DataTable } from './AsyncTab'

/** التبويب 6 — الأرشيف الورقي (يتطلّب archive.view — 403 يُعالَج بحالة صلاحية واضحة). */
export function ArchiveTab({ caseId }: { caseId: string }) {
  const q = useQuery({ queryKey: ['case', caseId, 'archive'], queryFn: () => fetchArchive(caseId) })

  return (
    <AsyncTab
      q={q}
      emptyMessage="لا توجد مواقع أرشيف مفهرسة لهذه القضية."
      render={(locations) => (
        <DataTable head={['عنوان الملف', 'الغرفة', 'الخزانة', 'الرف', 'الدرج', 'رقم الملف', 'ملاحظات']}>
          {locations.map((l) => (
            <tr key={l.id} className="border-b border-slate-100 last:border-0">
              <td data-label="عنوان الملف" className="px-4 py-3 font-medium text-slate-800">{l.file_title}</td>
              <td data-label="الغرفة" className="px-4 py-3 text-slate-600">{l.archive_room ?? '—'}</td>
              <td data-label="الخزانة" className="px-4 py-3 text-slate-600">{l.cabinet ?? '—'}</td>
              <td data-label="الرف" className="px-4 py-3 text-slate-600">{l.shelf ?? '—'}</td>
              <td data-label="الدرج" className="px-4 py-3 text-slate-600">{l.drawer ?? '—'}</td>
              <td data-label="رقم الملف" className="px-4 py-3 tabular-nums text-slate-600">{l.file_number ?? '—'}</td>
              <td data-label="ملاحظات" className="px-4 py-3 text-slate-600">{l.notes ?? '—'}</td>
            </tr>
          ))}
        </DataTable>
      )}
    />
  )
}
