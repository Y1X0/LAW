import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { formatDateTime } from '@/core/lib/format'
import { Button } from '@/core/ui/primitives'
import { EmptyState, ErrorState, Skeleton } from '@/core/ui/states'
import {
  fetchNotifications,
  markAllNotificationsRead,
  markNotificationRead,
  type NotificationItem,
} from '@/notifications/api'

/** سطر إشعار — عنوان (سميك إن كان غير مقروء) + نصّ + وقت، وزر تعليم كمقروء للمقروء لاحقاً. */
function Row({ item, onMarkRead, marking }: { item: NotificationItem; onMarkRead: (id: number) => void; marking: boolean }) {
  const unread = item.read_at === null
  return (
    <li className={`flex items-start gap-3 px-4 py-3 ${unread ? 'bg-brand-50/40' : ''}`}>
      <span
        aria-hidden="true"
        className={`mt-1.5 h-2 w-2 shrink-0 rounded-full ${unread ? 'bg-brand-500' : 'bg-transparent'}`}
      />
      <div className="min-w-0 flex-1">
        <div className={`truncate text-sm ${unread ? 'font-semibold text-slate-800' : 'text-slate-600'}`}>{item.title}</div>
        {item.body ? <p className="mt-0.5 line-clamp-2 text-xs text-slate-500">{item.body}</p> : null}
        <div className="mt-1 text-[11px] tabular-nums text-slate-400">{formatDateTime(item.created_at)}</div>
      </div>
      {unread ? (
        <button
          onClick={() => onMarkRead(item.id)}
          disabled={marking}
          className="lp-press shrink-0 rounded-md px-2 py-1 text-[11px] text-brand-700 hover:bg-brand-50 disabled:opacity-50"
        >
          تعليم كمقروء
        </button>
      ) : null}
    </li>
  )
}

/**
 * لوحة مركز الإشعارات (Phase 8 · PR-3) — قائمة + فلتر غير المقروء + تعليم فردي/شامل.
 * كل القيم من الخادم (لا حساب في الواجهة)؛ حالات تحميل/فارغ/خطأ بنمط الوحدات السابقة.
 */
export function NotificationsPanel({ onClose }: { onClose: () => void }) {
  const [unreadOnly, setUnreadOnly] = useState(false)
  const queryClient = useQueryClient()

  const { data, isPending, isError, error, refetch } = useQuery({
    queryKey: ['notifications', 'list', unreadOnly],
    queryFn: () => fetchNotifications(unreadOnly),
  })

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['notifications'] })

  const markOne = useMutation({ mutationFn: markNotificationRead, onSuccess: invalidate })
  const markAll = useMutation({ mutationFn: markAllNotificationsRead, onSuccess: invalidate })

  const unread = data?.unread ?? 0

  return (
    <div
      role="dialog"
      aria-label="مركز الإشعارات"
      className="absolute inset-x-0 top-full z-30 mt-2 max-h-[70vh] w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl ltr:right-auto sm:inset-x-auto sm:end-0"
    >
      <div className="flex items-center justify-between gap-2 border-b border-slate-100 px-4 py-3">
        <h2 className="text-sm font-semibold text-slate-800">الإشعارات</h2>
        <button onClick={onClose} aria-label="إغلاق" className="lp-press rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
          <svg viewBox="0 0 24 24" className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
            <path d="M6 6l12 12M18 6L6 18" />
          </svg>
        </button>
      </div>

      <div className="flex items-center justify-between gap-2 border-b border-slate-100 px-3 py-2">
        <div className="flex gap-1" role="tablist" aria-label="تصفية الإشعارات">
          <button
            role="tab"
            aria-selected={!unreadOnly}
            onClick={() => setUnreadOnly(false)}
            className={`rounded-md px-2.5 py-1 text-xs transition ${!unreadOnly ? 'bg-brand-50 font-semibold text-brand-700' : 'text-slate-500 hover:bg-slate-100'}`}
          >
            الكل
          </button>
          <button
            role="tab"
            aria-selected={unreadOnly}
            onClick={() => setUnreadOnly(true)}
            className={`rounded-md px-2.5 py-1 text-xs transition ${unreadOnly ? 'bg-brand-50 font-semibold text-brand-700' : 'text-slate-500 hover:bg-slate-100'}`}
          >
            غير المقروءة
          </button>
        </div>
        <button
          onClick={() => markAll.mutate()}
          disabled={unread === 0 || markAll.isPending}
          className="lp-press rounded-md px-2 py-1 text-xs text-brand-700 hover:bg-brand-50 disabled:opacity-40"
        >
          تعليم الكل كمقروء
        </button>
      </div>

      <div className="max-h-[52vh] overflow-y-auto">
        {isPending ? (
          <div className="space-y-2 p-4" data-testid="notifications-skeleton">
            {Array.from({ length: 3 }).map((_, i) => (
              <div key={i} className="flex gap-3">
                <Skeleton className="mt-1 h-2 w-2 rounded-full" />
                <div className="flex-1">
                  <Skeleton className="h-3.5 w-40" />
                  <Skeleton className="mt-2 h-3 w-24" />
                </div>
              </div>
            ))}
          </div>
        ) : isError ? (
          <div className="p-4">
            <ErrorState error={error}>
              <div className="mt-3">
                <Button onClick={() => void refetch()}>إعادة المحاولة</Button>
              </div>
            </ErrorState>
          </div>
        ) : data.items.length === 0 ? (
          <EmptyState message={unreadOnly ? 'لا إشعارات غير مقروءة.' : 'لا إشعارات بعد.'} />
        ) : (
          <ul className="divide-y divide-slate-100">
            {data.items.map((item) => (
              <Row key={item.id} item={item} onMarkRead={(id) => markOne.mutate(id)} marking={markOne.isPending} />
            ))}
          </ul>
        )}
      </div>
    </div>
  )
}
