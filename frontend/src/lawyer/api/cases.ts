import { z } from 'zod'
import { api } from '@/core/api/client'

/**
 * قائمة قضايا المحامي — المصدر الوحيد `GET /api/cases` (بعزل view_own على الخادم).
 * قراءة فقط؛ الحقول الإضافية في الحمولة تُتجاهَل.
 */
const clientRefSchema = z.object({ id: z.number(), name: z.string() }).nullable().optional()

export const caseListItemSchema = z.object({
  id: z.number(),
  internal_number: z.string(),
  court_case_number: z.string().nullable().optional(),
  title: z.string(),
  status: z.string(),
  progress: z.number(),
  case_type: z.string().nullable().optional(),
  opened_date: z.string().nullable().optional(),
  client: clientRefSchema,
})

export const paginationMetaSchema = z.object({
  page: z.number(),
  per_page: z.number(),
  total: z.number(),
  total_pages: z.number(),
})

export type CaseListItem = z.infer<typeof caseListItemSchema>
export type PaginationMeta = z.infer<typeof paginationMetaSchema>

export interface CaseListParams {
  search?: string
  status?: string
  page?: number
  perPage?: number
}

export interface CaseListResult {
  items: CaseListItem[]
  meta: PaginationMeta
}

/** حالات القضية المعتمدة (open/pending/closed) — للفلترة والعرض. */
export const CASE_STATUSES = ['open', 'pending', 'closed'] as const

const CASE_STATUS_LABEL: Record<string, string> = {
  open: 'مفتوحة',
  pending: 'معلّقة',
  closed: 'مغلقة',
}

export function caseStatusLabel(status: string): string {
  return CASE_STATUS_LABEL[status] ?? status
}

function buildQuery(params: CaseListParams): string {
  const q = new URLSearchParams()
  if (params.search) q.set('search', params.search)
  if (params.status) q.set('status', params.status)
  q.set('page', String(params.page ?? 1))
  q.set('per_page', String(params.perPage ?? 15))
  return q.toString()
}

export async function fetchCases(params: CaseListParams = {}): Promise<CaseListResult> {
  const env = await api.getPage<unknown>(`cases?${buildQuery(params)}`)
  return {
    items: z.array(caseListItemSchema).parse(env.data ?? []),
    meta: paginationMetaSchema.parse(env.meta),
  }
}
