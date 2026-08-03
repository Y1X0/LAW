import { z } from 'zod'
import { api } from '@/core/api/client'

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
