import { z } from 'zod'
import { api } from '@/core/api/client'

/**
 * ملف القضية (LP-4) — قراءة فقط. كل مورد يرث عزل القضية على الخادم.
 * مخطّطات Zod غير صارمة (تتجاهل الحقول الإضافية).
 */

// ---- 1) Overview: GET /api/cases/{id} ----
const lawyerRefSchema = z.object({ id: z.number(), full_name_ar: z.string() }).nullable().optional()

export const caseDetailSchema = z.object({
  id: z.number(),
  internal_number: z.string(),
  court_case_number: z.string().nullable().optional(),
  title: z.string(),
  court_name: z.string().nullable().optional(),
  case_type: z.string().nullable().optional(),
  value: z.union([z.string(), z.number()]).nullable().optional(),
  status: z.string(),
  progress: z.number(),
  opened_date: z.string().nullable().optional(),
  description: z.string().nullable().optional(),
  client: z
    .object({
      id: z.number(),
      name: z.string(),
      phone: z.string().nullable().optional(),
      email: z.string().nullable().optional(),
    })
    .nullable()
    .optional(),
  responsibleLawyer: lawyerRefSchema,
  assignments: z
    .array(z.object({ id: z.number().optional(), role: z.string().nullable().optional(), employee: lawyerRefSchema }))
    .optional()
    .default([]),
})
export type CaseDetail = z.infer<typeof caseDetailSchema>

// ---- 2) Parties ----
export const partySchema = z.object({
  id: z.number(),
  name: z.string(),
  type: z.string(),
  phone: z.string().nullable().optional(),
  notes: z.string().nullable().optional(),
})
export type Party = z.infer<typeof partySchema>

// ---- 3) Hearings ----
export const hearingSchema = z.object({
  id: z.number(),
  scheduled_at: z.string(),
  type: z.string().nullable().optional(),
  status: z.string(),
  location: z.string().nullable().optional(),
  outcome: z.string().nullable().optional(),
  postponed_to: z.string().nullable().optional(),
  postponed_reason: z.string().nullable().optional(),
  notes: z.string().nullable().optional(),
})
export type Hearing = z.infer<typeof hearingSchema>

// ---- 4) Timeline (append-only) ----
export const caseTimelineSchema = z.object({
  id: z.number(),
  title: z.string(),
  event_type: z.string().nullable().optional(),
  event_date: z.string(),
  description: z.string().nullable().optional(),
})
export type CaseTimelineEvent = z.infer<typeof caseTimelineSchema>

// ---- 5) Documents (metadata فقط) ----
export const documentSchema = z.object({
  id: z.number(),
  title: z.string(),
  document_type: z.string().nullable().optional(),
  description: z.string().nullable().optional(),
})
export type CaseDocument = z.infer<typeof documentSchema>

// ---- 6) Archive ----
export const archiveSchema = z.object({
  id: z.number(),
  file_title: z.string(),
  archive_room: z.string().nullable().optional(),
  cabinet: z.string().nullable().optional(),
  shelf: z.string().nullable().optional(),
  drawer: z.string().nullable().optional(),
  file_number: z.string().nullable().optional(),
  notes: z.string().nullable().optional(),
})
export type ArchiveLocation = z.infer<typeof archiveSchema>

// ---- 7) Tasks ----
export const caseTaskSchema = z.object({
  id: z.number(),
  title: z.string(),
  description: z.string().nullable().optional(),
  priority: z.string(),
  due_date: z.string().nullable().optional(),
  status: z.string(),
  completed_at: z.string().nullable().optional(),
  assignee: lawyerRefSchema,
})
export type CaseTask = z.infer<typeof caseTaskSchema>

// ---- fetchers ----
export const fetchCaseDetail = (id: string) => api.get<unknown>(`cases/${id}`).then((d) => caseDetailSchema.parse(d))
export const fetchParties = (id: string) => api.get<unknown>(`cases/${id}/parties`).then((d) => z.array(partySchema).parse(d ?? []))
export const fetchHearings = (id: string) => api.get<unknown>(`cases/${id}/hearings`).then((d) => z.array(hearingSchema).parse(d ?? []))
export const fetchCaseTimeline = (id: string) => api.get<unknown>(`cases/${id}/timeline`).then((d) => z.array(caseTimelineSchema).parse(d ?? []))
export const fetchDocuments = (id: string) => api.get<unknown>(`cases/${id}/documents`).then((d) => z.array(documentSchema).parse(d ?? []))
export const fetchArchive = (id: string) => api.get<unknown>(`cases/${id}/archive-locations`).then((d) => z.array(archiveSchema).parse(d ?? []))
export const fetchCaseTasks = (id: string) =>
  api.getPage<unknown>(`tasks?case_id=${id}&per_page=100`).then((env) => z.array(caseTaskSchema).parse(env.data ?? []))

// ---- تسميات عربية (عرض فقط) ----
const PARTY_TYPE: Record<string, string> = {
  plaintiff: 'مدّعٍ',
  defendant: 'مدّعى عليه',
  witness: 'شاهد',
  other: 'أخرى',
}
export const partyTypeLabel = (t: string): string => PARTY_TYPE[t] ?? t

const TASK_PRIORITY: Record<string, string> = { low: 'منخفضة', normal: 'عادية', high: 'عالية' }
export const taskPriorityLabel = (p: string): string => TASK_PRIORITY[p] ?? p

const TASK_STATUS: Record<string, string> = { open: 'مفتوحة', done: 'منجزة' }
export const taskStatusLabel = (s: string): string => TASK_STATUS[s] ?? s
