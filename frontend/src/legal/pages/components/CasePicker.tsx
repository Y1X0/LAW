import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Button } from '@/core/ui/primitives'
import { EmptyState, ErrorState, Skeleton } from '@/core/ui/states'
import { fetchCases } from '@/legal/api/cases'

/**
 * منتقي قضية اختياري (Phase 3 / PR-6) — لربط المهمة بقضية عبر البحث في `GET /cases`.
 * اختياري: المهمة قد تكون عامّة (case_id = null) على الخادم.
 */
export function CasePicker({
  selected,
  onSelect,
  onClear,
  disabled,
}: {
  selected: { id: number; label: string } | null
  onSelect: (c: { id: number; label: string }) => void
  onClear: () => void
  disabled?: boolean
}) {
  const [search, setSearch] = useState('')
  const results = useQuery({
    queryKey: ['legal', 'task-case-search', search],
    queryFn: () => fetchCases({ search: search.trim(), perPage: 8 }),
    enabled: !selected && search.trim().length >= 1,
  })

  if (selected) {
    return (
      <div className="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-sm">
        <span className="font-medium text-slate-800">{selected.label}</span>
        <Button variant="ghost" onClick={onClear} disabled={disabled}>إزالة</Button>
      </div>
    )
  }

  return (
    <div className="space-y-2">
      <label className="block text-sm font-medium text-slate-700">
        القضية (اختياري)
        <input
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="رقم داخلي · عنوان"
          className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
        />
      </label>
      {search.trim().length >= 1 && (
        results.isPending ? <Skeleton className="h-14 w-full" /> :
        results.isError ? <ErrorState error={results.error} /> :
        results.data.items.length === 0 ? <EmptyState message="لا توجد قضية مطابقة." /> : (
          <ul className="space-y-2">
            {results.data.items.map((c) => (
              <li key={c.id}>
                <button type="button" onClick={() => onSelect({ id: c.id, label: `${c.internal_number} · ${c.title}` })} className="lp-press flex w-full items-center justify-between gap-2 rounded-lg border border-slate-200 px-3 py-2 text-right hover:bg-brand-50">
                  <span className="min-w-0 truncate font-medium text-slate-800">{c.title}</span>
                  <span className="tabular-nums text-xs text-slate-400">{c.internal_number}</span>
                </button>
              </li>
            ))}
          </ul>
        )
      )}
    </div>
  )
}
