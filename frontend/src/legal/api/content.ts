import { z } from 'zod'
import { api, apiRequest } from '@/core/api/client'

/* ============================================================
   محتوى القضية (Phase 3 / PR-5) — وثائق (بيانات وصفية) · أطراف · خط زمني
   (append-only) · أرشيف ورقي. فوق العقود الموجودة فقط. الواجهة تعكس قيود الخادم:
   لا رفع ملف فعلي، لا تعديل/حذف طرف، لا تعديل/حذف حدث تاريخي.
   ============================================================ */

// ---- الوثائق (بيانات وصفية فقط — لا رفع ملف) ----
export const documentSchema = z.object({
  id: z.number(),
  title: z.string(),
  document_type: z.string().nullable().optional(),
  description: z.string().nullable().optional(),
})
export type CaseDocument = z.infer<typeof documentSchema>

export function fetchDocuments(caseId: number): Promise<CaseDocument[]> {
  return api.get<unknown>(`cases/${caseId}/documents`).then((d) => z.array(documentSchema).parse(d ?? []))
}
export function createDocument(caseId: number, input: { title: string; document_type?: string | null; description?: string | null }): Promise<CaseDocument> {
  return api.post<unknown>(`cases/${caseId}/documents`, input).then((d) => documentSchema.parse(d))
}
export function deleteDocument(id: number): Promise<unknown> {
  return apiRequest<unknown>(`documents/${id}`, { method: 'DELETE' })
}

// ---- الأطراف (إضافة/عرض فقط — لا تعديل/حذف على الخادم) ----
export const PARTY_TYPES = ['plaintiff', 'defendant', 'witness', 'other'] as const
const PARTY_TYPE_LABEL: Record<string, string> = { plaintiff: 'مدّعٍ', defendant: 'مدّعى عليه', witness: 'شاهد', other: 'آخر' }
export function partyTypeLabel(t: string): string {
  return PARTY_TYPE_LABEL[t] ?? t
}
export const partySchema = z.object({
  id: z.number(),
  name: z.string(),
  type: z.string(),
  phone: z.string().nullable().optional(),
  notes: z.string().nullable().optional(),
})
export type CaseParty = z.infer<typeof partySchema>

export function fetchParties(caseId: number): Promise<CaseParty[]> {
  return api.get<unknown>(`cases/${caseId}/parties`).then((d) => z.array(partySchema).parse(d ?? []))
}
export function createParty(caseId: number, input: { name: string; type: string; phone?: string | null; notes?: string | null }): Promise<CaseParty> {
  return api.post<unknown>(`cases/${caseId}/parties`, input).then((d) => partySchema.parse(d))
}

// ---- الخط الزمني (append-only — لا تعديل/حذف) ----
export const timelineSchema = z.object({
  id: z.number(),
  title: z.string(),
  event_type: z.string().nullable().optional(),
  event_date: z.string().nullable().optional(),
  description: z.string().nullable().optional(),
})
export type TimelineEvent = z.infer<typeof timelineSchema>

export function fetchTimeline(caseId: number): Promise<TimelineEvent[]> {
  return api.get<unknown>(`cases/${caseId}/timeline`).then((d) => z.array(timelineSchema).parse(d ?? []))
}
export function createTimelineEvent(caseId: number, input: { title: string; event_type?: string | null; event_date: string; description?: string | null }): Promise<TimelineEvent> {
  return api.post<unknown>(`cases/${caseId}/timeline`, input).then((d) => timelineSchema.parse(d))
}

// ---- الأرشيف الورقي (CRUD مواقع) ----
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
export interface ArchiveInput {
  file_title: string
  archive_room?: string | null
  cabinet?: string | null
  shelf?: string | null
  drawer?: string | null
  file_number?: string | null
  notes?: string | null
}

export function fetchArchive(caseId: number): Promise<ArchiveLocation[]> {
  return api.get<unknown>(`cases/${caseId}/archive-locations`).then((d) => z.array(archiveSchema).parse(d ?? []))
}
export function createArchive(caseId: number, input: ArchiveInput): Promise<ArchiveLocation> {
  return api.post<unknown>(`cases/${caseId}/archive-locations`, input).then((d) => archiveSchema.parse(d))
}
export function updateArchive(id: number, input: Partial<ArchiveInput>): Promise<ArchiveLocation> {
  return apiRequest<unknown>(`archive-locations/${id}`, { method: 'PUT', body: input }).then((d) => archiveSchema.parse(d))
}
export function deleteArchive(id: number): Promise<unknown> {
  return apiRequest<unknown>(`archive-locations/${id}`, { method: 'DELETE' })
}
