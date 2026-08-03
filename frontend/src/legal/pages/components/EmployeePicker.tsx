import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Button } from '@/core/ui/primitives'
import { EmptyState, ErrorState, Skeleton } from '@/core/ui/states'
import { fetchEmployees, type EmployeeListItem } from '@/hr/api/employees'

/**
 * منتقي موظف بالبحث (Phase 3 / PR-6) — مستخرَج ليعاد استخدامه في إنشاء المهمة
 * وإعادة إسنادها. يطابق نمط منتقي المحامي في AssignLawyerModal.
 */
export function EmployeePicker({
  selected,
  onSelect,
  onClear,
  disabled,
  label = 'الموظف المسؤول *',
}: {
  selected: { id: number; name: string } | null
  onSelect: (emp: { id: number; name: string }) => void
  onClear: () => void
  disabled?: boolean
  label?: string
}) {
  const [search, setSearch] = useState('')
  const results = useQuery({
    queryKey: ['legal', 'task-emp-search', search],
    queryFn: () => fetchEmployees({ search: search.trim(), perPage: 10 }),
    enabled: !selected && search.trim().length >= 1,
  })

  if (selected) {
    return (
      <div className="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-sm">
        <span className="font-medium text-slate-800">{selected.name}</span>
        <Button variant="ghost" onClick={onClear} disabled={disabled}>تغيير</Button>
      </div>
    )
  }

  return (
    <div className="space-y-2">
      <label className="block text-sm font-medium text-slate-700">
        {label}
        <input
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="الاسم أو الرقم الوظيفي"
          className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
        />
      </label>
      {search.trim().length >= 1 && (
        results.isPending ? <Skeleton className="h-14 w-full" /> :
        results.isError ? <ErrorState error={results.error} /> :
        results.data.items.length === 0 ? <EmptyState message="لا يوجد موظف مطابق." /> : (
          <ul className="space-y-2">
            {results.data.items.map((e: EmployeeListItem) => (
              <li key={e.id}>
                <button type="button" onClick={() => onSelect({ id: e.id, name: e.full_name_ar })} className="lp-press flex w-full items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-right hover:bg-brand-50">
                  <span className="font-medium text-slate-800">{e.full_name_ar}</span>
                  <span className="tabular-nums text-xs text-slate-400">{e.employee_no}</span>
                </button>
              </li>
            ))}
          </ul>
        )
      )}
    </div>
  )
}
