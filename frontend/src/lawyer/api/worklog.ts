import { z } from 'zod'
import { api } from '@/core/api/client'

/**
 * الإنجاز اليومي الذاتي — المصدر `GET/POST /api/me/worklog`.
 * POST يعمل upsert لسجل *اليوم* على الخادم (لا يمكن تعديل أيام سابقة).
 */
export const worklogSchema = z.object({
  id: z.number(),
  employee_id: z.number().optional(),
  work_date: z.string(),
  done_today: z.string().nullable().optional(),
  plan_tomorrow: z.string().nullable().optional(),
  updated_at: z.string().nullable().optional(),
})
export type Worklog = z.infer<typeof worklogSchema>

export interface WorklogInput {
  done_today: string
  plan_tomorrow?: string | null
}

export const fetchMyWorklog = () =>
  api.get<unknown>('me/worklog').then((d) => z.array(worklogSchema).parse(d ?? []))

export const submitWorklog = (body: WorklogInput) =>
  api.post<unknown>('me/worklog', body).then((d) => worklogSchema.parse(d))

/** تاريخ اليوم المحلي "YYYY-MM-DD" — لمطابقة سجل اليوم القادم من الخادم. */
export function todayISO(): string {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}
