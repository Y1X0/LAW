import { z } from 'zod'
import { api } from '@/core/api/client'

/**
 * لوحة المحامي — المصدر الوحيد `GET /api/me/legal-summary` (تجميع ذاتي مُنطَّق
 * على القضايا المسندة على الخادم). قراءة فقط؛ الحقول الإضافية في الحمولة تُتجاهَل.
 */
const caseRefSchema = z
  .object({ id: z.number(), internal_number: z.string(), title: z.string() })
  .nullable()
  .optional()

const nextHearingSchema = z
  .object({
    id: z.number(),
    scheduled_at: z.string(),
    type: z.string().nullable().optional(),
    status: z.string(),
    location: z.string().nullable().optional(),
    case: caseRefSchema,
  })
  .nullable()

const timelineEventSchema = z.object({
  id: z.number(),
  case_id: z.number(),
  title: z.string(),
  event_date: z.string(),
})

const worklogSchema = z
  .object({
    id: z.number(),
    work_date: z.string(),
    done_today: z.string().nullable(),
  })
  .nullable()

export const legalSummarySchema = z.object({
  cases: z.object({
    total: z.number(),
    open: z.number(),
    pending: z.number(),
    closed: z.number(),
  }),
  tasks: z.object({ pending: z.number() }),
  next_hearing: nextHearingSchema,
  recent_events: z.array(timelineEventSchema),
  last_worklog: worklogSchema,
})

export type LegalSummary = z.infer<typeof legalSummarySchema>
export type NextHearing = z.infer<typeof nextHearingSchema>
export type TimelineEvent = z.infer<typeof timelineEventSchema>

export async function fetchLegalSummary(): Promise<LegalSummary> {
  const data = await api.get<unknown>('me/legal-summary')
  return legalSummarySchema.parse(data)
}

const HEARING_STATUS: Record<string, string> = {
  scheduled: 'مجدولة',
  held: 'منعقدة',
  postponed: 'مؤجّلة',
  cancelled: 'ملغاة',
}

/** تسمية عربية لحالة الجلسة (عرض فقط). */
export function hearingStatusLabel(status: string): string {
  return HEARING_STATUS[status] ?? status
}
