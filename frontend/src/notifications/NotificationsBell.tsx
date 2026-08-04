import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Icon } from '@/core/layout/icons'
import { fetchUnreadCount } from '@/notifications/api'
import { NotificationsPanel } from '@/notifications/NotificationsPanel'

/**
 * جرس الإشعارات في رأس الهيكل (Phase 8 · PR-3). عدّاد غير المقروء من الخادم مباشرة
 * (لا يُحسب في الواجهة)؛ نقر الجرس يفتح مركز الإشعارات. لا حارس صلاحية في الواجهة —
 * الخادم هو الحكم: إن لم يُسمح للمستخدم يعود العدّاد بخطأ فيظهر الجرس بلا شارة.
 */
export function NotificationsBell() {
  const [open, setOpen] = useState(false)

  const { data } = useQuery({
    queryKey: ['notifications', 'unread'],
    queryFn: fetchUnreadCount,
    // تحديث خفيف عند العودة للنافذة فقط — لا polling ذكي/WebSocket في هذه المرحلة.
    refetchOnWindowFocus: true,
  })
  const unread = data ?? 0
  const label = unread > 0 ? `الإشعارات (${unread} غير مقروء)` : 'الإشعارات'

  return (
    <div className="relative">
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        aria-label={label}
        aria-expanded={open}
        aria-haspopup="dialog"
        className="lp-press relative flex h-9 w-9 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 hover:text-slate-700"
      >
        <Icon name="bell" className="h-5 w-5" />
        {unread > 0 ? (
          <span
            aria-hidden="true"
            className="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold leading-none text-white"
          >
            {unread > 99 ? '99+' : unread}
          </span>
        ) : null}
      </button>

      {open ? (
        <>
          {/* طبقة إغلاق عند النقر خارج اللوحة. */}
          <button
            type="button"
            aria-hidden="true"
            tabIndex={-1}
            onClick={() => setOpen(false)}
            className="fixed inset-0 z-20 cursor-default"
          />
          <NotificationsPanel onClose={() => setOpen(false)} />
        </>
      ) : null}
    </div>
  )
}
