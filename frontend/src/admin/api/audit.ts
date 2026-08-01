import { z } from 'zod'
import { api } from '@/core/api/client'
import { activityLabel } from './summary'

export { activityLabel }

export const auditEntrySchema = z.object({
  id: z.number(),
  action: z.string(),
  user: z.object({ id: z.number(), name: z.string() }).nullable(),
  auditable_type: z.string().nullable().optional(),
  auditable_id: z.number().nullable().optional(),
  ip_address: z.string().nullable().optional(),
  created_at: z.string().nullable().optional(),
})
export type AuditEntry = z.infer<typeof auditEntrySchema>

const paginationMetaSchema = z.object({
  page: z.number(),
  per_page: z.number(),
  total: z.number(),
  total_pages: z.number(),
})
export type PaginationMeta = z.infer<typeof paginationMetaSchema>

export interface AuditListParams {
  action?: string
  page?: number
  perPage?: number
}

function buildQuery(params: AuditListParams): string {
  const q = new URLSearchParams()
  if (params.action) q.set('action', params.action)
  q.set('page', String(params.page ?? 1))
  q.set('per_page', String(params.perPage ?? 20))
  return q.toString()
}

export interface AuditListResult {
  items: AuditEntry[]
  meta: PaginationMeta
}

/** سجلّ التدقيق — `GET /admin/audit` (يحرسها الخادم بصلاحية audit.view). */
export async function fetchAudit(params: AuditListParams = {}): Promise<AuditListResult> {
  const env = await api.getPage<unknown>(`admin/audit?${buildQuery(params)}`)
  return {
    items: z.array(auditEntrySchema).parse(env.data ?? []),
    meta: paginationMetaSchema.parse(env.meta),
  }
}
