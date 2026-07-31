import type { ReactNode } from 'react'
import type { UseQueryResult } from '@tanstack/react-query'
import { Button } from '@/core/ui/primitives'
import { EmptyState, ErrorState, LoadingState } from '@/core/ui/states'

/**
 * غلاف حالات موحّد لتبويبات ملف القضية (قائمة): Loading · Error+retry · Empty · بيانات.
 * يعالج 403 لكل تبويب (عبر ErrorState → PermissionDenied) — مثل تبويبَي الأرشيف/المهام.
 */
export function AsyncTab<T>({
  q,
  emptyMessage,
  render,
}: {
  q: UseQueryResult<T[]>
  emptyMessage: string
  render: (items: T[]) => ReactNode
}) {
  if (q.isPending) return <LoadingState />
  if (q.isError) {
    return (
      <ErrorState error={q.error}>
        <div className="mt-3">
          <Button onClick={() => void q.refetch()}>إعادة المحاولة</Button>
        </div>
      </ErrorState>
    )
  }
  if (q.data.length === 0) return <EmptyState message={emptyMessage} />
  return <>{render(q.data)}</>
}

/** جدول بسيط RTL بعناوين — يُعاد استخدامه عبر التبويبات. */
export function DataTable({ head, children }: { head: string[]; children: ReactNode }) {
  return (
    <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white">
      <table className="w-full min-w-[640px] text-right text-sm">
        <thead>
          <tr className="border-b border-slate-200 text-xs text-slate-500">
            {head.map((h) => (
              <th key={h} className="px-4 py-3 font-medium">
                {h}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>{children}</tbody>
      </table>
    </div>
  )
}
