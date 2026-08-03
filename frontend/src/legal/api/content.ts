import { z } from 'zod'
import { api, apiRequest } from '@/core/api/client'

/* ============================================================
   محتوى القضية — أطراف · خط زمني (append-only) · أرشيف ورقي فوق العقود الموجودة.
   الوثائق (Phase 5 / PR-2): رفع فعلي إلى R2 + تنزيل محروس عبر الخادم. الواجهة تعكس
   قيود الخادم: لا تعديل/حذف طرف، لا تعديل/حذف حدث تاريخي.
   ============================================================ */

// ---- الوثائق (رفع/تنزيل فعلي — Phase 5) ----
export const DOCUMENT_MAX_MB = 20
export const DOCUMENT_ACCEPT = '.pdf,.jpg,.jpeg,.png,.doc,.docx'

export const documentSchema = z.object({
  id: z.number(),
  title: z.string(),
  document_type: z.string().nullable().optional(),
  description: z.string().nullable().optional(),
  original_name: z.string().nullable().optional(),
  mime_type: z.string().nullable().optional(),
  size_bytes: z.number().nullable().optional(),
  created_at: z.string().nullable().optional(),
})
export type CaseDocument = z.infer<typeof documentSchema>

export function fetchDocuments(caseId: number): Promise<CaseDocument[]> {
  return api.get<unknown>(`cases/${caseId}/documents`).then((d) => z.array(documentSchema).parse(d ?? []))
}

/** رفع مستند فعلي (multipart) — الخادم يقرّر المسار/القرص ويفحص النوع والحجم. */
export function createDocument(caseId: number, input: { title: string; document_type?: string | null; description?: string | null; file: File }): Promise<CaseDocument> {
  const form = new FormData()
  form.set('title', input.title)
  if (input.document_type) form.set('document_type', input.document_type)
  if (input.description) form.set('description', input.description)
  form.set('file', input.file)
  return api.upload<unknown>(`cases/${caseId}/documents`, form).then((d) => documentSchema.parse(d))
}

export function deleteDocument(id: number): Promise<unknown> {
  return apiRequest<unknown>(`documents/${id}`, { method: 'DELETE' })
}

/** تنزيل محروس: يجلب الملف بمصادقة (بعد حارس رؤية القضية) ويحفظه بالاسم الأصلي. */
export async function downloadDocument(doc: CaseDocument): Promise<void> {
  const blob = await api.blob(`documents/${doc.id}/download`)
  const url = URL.createObjectURL(blob)
  try {
    const a = document.createElement('a')
    a.href = url
    a.download = doc.original_name || doc.title || 'document'
    document.body.appendChild(a)
    a.click()
    a.remove()
  } finally {
    URL.revokeObjectURL(url)
  }
}

/** حجم مقروء بشري (KB/MB) للعرض. */
export function formatFileSize(bytes?: number | null): string {
  if (!bytes || bytes <= 0) return ''
  if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} كB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} مB`
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
