import { z } from 'zod'
import { api, apiRequest } from '@/core/api/client'

/* ============================================================
   جلسات القضية (Phase 3 / PR-4) — فوق العقود الموجودة فقط.
   قاعدة الخادم: الجلسة held مجمّدة (لا تُعدَّل/تُؤجَّل/تُلغى) — تعكسها الواجهة.
   ============================================================ */

export const HEARING_STATUSES = ['scheduled', 'held', 'postponed', 'cancelled'] as const
const STATUS_LABEL: Record<string, string> = { scheduled: 'مجدولة', held: 'منعقدة', postponed: 'مؤجّلة', cancelled: 'ملغاة' }
export function hearingStatusLabel(s: string): string {
  return STATUS_LABEL[s] ?? s
}
export function hearingStatusTone(s: string): 'green' | 'amber' | 'slate' | 'navy' {
  if (s === 'scheduled') return 'green'
  if (s === 'postponed') return 'amber'
  if (s === 'held') return 'navy'
  return 'slate' // cancelled
}
/** الجلسة المنعقدة مجمّدة على الخادم (guardNotHeld) — تُعطَّل إجراءاتها في الواجهة. */
export function isHearingFrozen(status: string): boolean {
  return status === 'held'
}

/** عرض مختصر للتاريخ/الوقت (YYYY-MM-DD HH:mm) من قيمة ISO. */
export function formatDateTime(v: string | null | undefined): string {
  if (!v) return '—'
  return v.replace('T', ' ').slice(0, 16)
}

export const hearingSchema = z.object({
  id: z.number(),
  case_id: z.number().nullable().optional(),
  scheduled_at: z.string().nullable().optional(),
  type: z.string().nullable().optional(),
  status: z.string(),
  location: z.string().nullable().optional(),
  outcome: z.string().nullable().optional(),
  postponed_to: z.string().nullable().optional(),
  postponed_reason: z.string().nullable().optional(),
  notes: z.string().nullable().optional(),
})
export type Hearing = z.infer<typeof hearingSchema>

export function fetchCaseHearings(caseId: number): Promise<Hearing[]> {
  return api.get<unknown>(`cases/${caseId}/hearings`).then((d) => z.array(hearingSchema).parse(d ?? []))
}

export interface HearingInput {
  scheduled_at: string
  type?: string | null
  location?: string | null
  notes?: string | null
}

export function createHearing(caseId: number, input: HearingInput): Promise<Hearing> {
  return api.post<unknown>(`cases/${caseId}/hearings`, input).then((d) => hearingSchema.parse(d))
}

export interface HearingUpdate {
  scheduled_at?: string
  type?: string | null
  status?: string
  location?: string | null
  outcome?: string | null
  notes?: string | null
}

export function updateHearing(id: number, input: HearingUpdate): Promise<Hearing> {
  return apiRequest<unknown>(`hearings/${id}`, { method: 'PUT', body: input }).then((d) => hearingSchema.parse(d))
}

/** إلغاء الجلسة — `POST /hearings/{id}/cancel` (يرفضه الخادم إن كانت منعقدة). */
export function cancelHearing(id: number): Promise<unknown> {
  return api.post<unknown>(`hearings/${id}/cancel`)
}

/** تأجيل الجلسة — يؤرشف القديمة (postponed) وينشئ جديدة (scheduled) على الخادم. */
export function postponeHearing(id: number, scheduledAt: string, reason: string): Promise<unknown> {
  return api.post<unknown>(`hearings/${id}/postpone`, { scheduled_at: scheduledAt, postponed_reason: reason })
}
