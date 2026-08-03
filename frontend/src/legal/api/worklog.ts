import { z } from 'zod'
import { api } from '@/core/api/client'

/* ============================================================
   الإنجاز اليومي — جانب الإشراف (Phase 3 / PR-6) من `GET /worklog` الموجود
   (يحرسه worklog.view_all). قراءة/إشراف فقط: المدير يطّلع على سجلات الجميع.
   التسجيل (POST me/worklog) ذاتي في بوابة المحامي ويشترط ربط موظف — لا يُبنى هنا.
   ============================================================ */

const employeeRefSchema = z.object({ id: z.number(), full_name_ar: z.string() }).nullable().optional()

export const worklogSchema = z.object({
  id: z.number(),
  employee_id: z.number().nullable().optional(),
  work_date: z.string(),
  done_today: z.string().nullable().optional(),
  plan_tomorrow: z.string().nullable().optional(),
  employee: employeeRefSchema,
})
export type Worklog = z.infer<typeof worklogSchema>

const paginationMetaSchema = z.object({
  page: z.number(),
  per_page: z.number(),
  total: z.number(),
  total_pages: z.number(),
})
export type PaginationMeta = z.infer<typeof paginationMetaSchema>

export interface WorklogListParams {
  employeeId?: number
  page?: number
  perPage?: number
}
export interface WorklogListResult {
  items: Worklog[]
  meta: PaginationMeta
}

function buildQuery(p: WorklogListParams): string {
  const q = new URLSearchParams()
  if (p.employeeId) q.set('employee_id', String(p.employeeId))
  q.set('page', String(p.page ?? 1))
  q.set('per_page', String(p.perPage ?? 15))
  return q.toString()
}

export function fetchAllWorklog(params: WorklogListParams = {}): Promise<WorklogListResult> {
  return api.getPage<unknown>(`worklog?${buildQuery(params)}`).then((env) => ({
    items: z.array(worklogSchema).parse(env.data ?? []),
    meta: paginationMetaSchema.parse(env.meta),
  }))
}
