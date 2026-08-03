import { z } from 'zod'
import { api, apiRequest } from '@/core/api/client'

/* ============================================================
   الإدارة القانونية — القضايا (Phase 3 / PR-1) من `GET /cases` الموجود.
   مدير قانوني (cases.view_all) يرى كل القضايا؛ نفس نقطة النهاية بلا عزل view_own.
   ============================================================ */

const refSchema = z.object({ id: z.number(), name: z.string() }).nullable().optional()
const lawyerRefSchema = z.object({ id: z.number(), full_name_ar: z.string() }).nullable().optional()

export const caseRowSchema = z.object({
  id: z.number(),
  internal_number: z.string(),
  court_case_number: z.string().nullable().optional(),
  title: z.string(),
  status: z.string(),
  progress: z.number().nullable().optional().transform((v) => v ?? 0),
  case_type: z.string().nullable().optional(),
  opened_date: z.string().nullable().optional(),
  client: refSchema,
  responsibleLawyer: lawyerRefSchema,
})
export type CaseRow = z.infer<typeof caseRowSchema>

const paginationMetaSchema = z.object({
  page: z.number(),
  per_page: z.number(),
  total: z.number(),
  total_pages: z.number(),
})
export type PaginationMeta = z.infer<typeof paginationMetaSchema>

export const CASE_STATUSES = ['open', 'pending', 'closed'] as const
const CASE_STATUS_LABEL: Record<string, string> = { open: 'مفتوحة', pending: 'معلّقة', closed: 'مغلقة' }
export function caseStatusLabel(s: string): string {
  return CASE_STATUS_LABEL[s] ?? s
}
export function caseStatusTone(s: string): 'green' | 'amber' | 'slate' | 'navy' {
  if (s === 'open') return 'green'
  if (s === 'pending') return 'amber'
  return 'slate' // closed
}

export interface CaseListParams {
  search?: string
  status?: string
  clientId?: number
  caseType?: string
  page?: number
  perPage?: number
}

function buildQuery(p: CaseListParams): string {
  const q = new URLSearchParams()
  if (p.search) q.set('search', p.search)
  if (p.status) q.set('status', p.status)
  if (p.clientId) q.set('client_id', String(p.clientId))
  if (p.caseType) q.set('case_type', p.caseType)
  q.set('page', String(p.page ?? 1))
  q.set('per_page', String(p.perPage ?? 15))
  return q.toString()
}

export interface CaseListResult {
  items: CaseRow[]
  meta: PaginationMeta
}

/** قائمة القضايا (كلّها لمن يملك cases.view_all) — `GET /cases`. */
export async function fetchCases(params: CaseListParams = {}): Promise<CaseListResult> {
  const env = await api.getPage<unknown>(`cases?${buildQuery(params)}`)
  return {
    items: z.array(caseRowSchema).parse(env.data ?? []),
    meta: paginationMetaSchema.parse(env.meta),
  }
}

// ---- تفاصيل القضية + إنشاء/تعديل/إغلاق (PR-2) ----
const money = z.union([z.string(), z.number(), z.null()]).optional().transform((v) => (v == null || v === '' ? null : Number(v)))

export const caseDetailSchema = caseRowSchema.extend({
  court_name: z.string().nullable().optional(),
  value: money,
  description: z.string().nullable().optional(),
  responsible_lawyer_id: z.number().nullable().optional(),
  assignments: z
    .array(z.object({
      id: z.number(),
      role: z.string(),
      employee: z.object({ id: z.number(), full_name_ar: z.string() }).nullable().optional(),
    }))
    .default([]),
})
export type CaseDetail = z.infer<typeof caseDetailSchema>

export function fetchCase(id: number): Promise<CaseDetail> {
  return api.get<unknown>(`cases/${id}`).then((d) => caseDetailSchema.parse(d))
}

export interface CaseInput {
  internal_number: string
  title: string
  client_id: number
  court_case_number?: string | null
  court_name?: string | null
  case_type?: string | null
  value?: number | null
  status?: string
  progress?: number
  opened_date?: string | null
  description?: string | null
}

export async function createCase(input: CaseInput): Promise<CaseDetail> {
  return caseDetailSchema.parse(await api.post<unknown>('cases', input))
}

export async function updateCase(id: number, input: Partial<CaseInput>): Promise<CaseDetail> {
  return caseDetailSchema.parse(await apiRequest<unknown>(`cases/${id}`, { method: 'PUT', body: input }))
}

/** إغلاق القضية — `POST /cases/{id}/close` (يحرسها cases.close). */
export async function closeCase(id: number): Promise<CaseDetail> {
  return caseDetailSchema.parse(await api.post<unknown>(`cases/${id}/close`))
}

// ---- إسناد المحامين (PR-3) — POST/DELETE الموجودان (يحرسهما cases.assign) ----
export const ASSIGNMENT_ROLES = ['lead', 'support'] as const
const ROLE_LABEL: Record<string, string> = { lead: 'رئيسي', support: 'مساند' }
export function assignmentRoleLabel(r: string): string {
  return ROLE_LABEL[r] ?? r
}

/** إسناد محامٍ للقضية — `POST /cases/{id}/assign`. */
export function assignLawyer(caseId: number, employeeId: number, role: string): Promise<unknown> {
  return api.post<unknown>(`cases/${caseId}/assign`, { employee_id: employeeId, role })
}

/** إلغاء إسناد محامٍ — `DELETE /cases/{id}/assign/{employee}`. */
export function unassignLawyer(caseId: number, employeeId: number): Promise<unknown> {
  return apiRequest<unknown>(`cases/${caseId}/assign/${employeeId}`, { method: 'DELETE' })
}
