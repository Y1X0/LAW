import type { ReactNode } from 'react'
import type { UseQueryResult } from '@tanstack/react-query'
import { Button } from '@/core/ui/primitives'
import { EmptyState, ErrorState, LoadingState } from '@/core/ui/states'

/**
 * غلاف حالات موحّد لتبويبات ملف الموظف: Loading · Error+retry · Empty · بيانات.
 * ErrorState يميّز 403 تلقائياً (صلاحية غير متاحة — مثل الرواتب/الإجازات لبعض المستخدمين).
 */
export function AsyncPanel<T>({
  q,
  isEmpty,
  emptyMessage,
  children,
}: {
  q: UseQueryResult<T>
  isEmpty?: (data: T) => boolean
  emptyMessage: string
  children: (data: T) => ReactNode
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
  if (isEmpty?.(q.data)) return <EmptyState message={emptyMessage} />
  return <>{children(q.data)}</>
}
