import { z } from 'zod'
import { api } from '@/core/api/client'

/**
 * طبقة API للإشعارات (Notifications / Phase 8 · PR-3) — قراءة ذاتية وتعليم كمقروء.
 * الواجهة لا تحسب عدّاد غير المقروء؛ تأخذه من `meta.unread` (القائمة) أو من نقطة العدّاد.
 */
export const notificationSchema = z.object({
  id: z.number(),
  type: z.string(),
  title: z.string(),
  body: z.string().nullable(),
  related_type: z.string().nullable(),
  related_id: z.number().nullable(),
  read_at: z.string().nullable(),
  created_at: z.string().nullable(),
})
export type NotificationItem = z.infer<typeof notificationSchema>

export interface NotificationsPage {
  items: NotificationItem[]
  /** عدّاد غير المقروء كما يعيده الخادم في meta — لا يُحسب في الواجهة. */
  unread: number
}

const unreadMetaSchema = z.object({ unread: z.number() })

/** GET /api/notifications (?unread=1) — قائمتي + عدّاد غير المقروء من meta. */
export async function fetchNotifications(unreadOnly = false): Promise<NotificationsPage> {
  const env = await api.getPage<unknown>(`notifications${unreadOnly ? '?unread=1' : ''}`)

  return {
    items: z.array(notificationSchema).parse(env.data ?? []),
    unread: unreadMetaSchema.parse(env.meta ?? { unread: 0 }).unread,
  }
}

/** GET /api/notifications/unread-count — العدّاد فقط (للجرس). */
export async function fetchUnreadCount(): Promise<number> {
  return unreadMetaSchema.parse(await api.get<unknown>('notifications/unread-count')).unread
}

/** PATCH /api/notifications/{id}/read — تعليم إشعار كمقروء. */
export async function markNotificationRead(id: number): Promise<void> {
  await api.patch(`notifications/${id}/read`)
}

/** PATCH /api/notifications/read-all — تعليم الكل كمقروء. */
export async function markAllNotificationsRead(): Promise<void> {
  await api.patch('notifications/read-all')
}
