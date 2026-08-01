import { z } from 'zod'
import { api, apiRequest } from '@/core/api/client'

/** حالات الموظف المعتمدة (من الباك-إند) — للفلترة والعرض. */
export const EMPLOYEE_STATUSES = ['active', 'on_leave', 'suspended', 'terminated'] as const

const STATUS_LABEL: Record<string, string> = {
  active: 'نشط',
  on_leave: 'في إجازة',
  suspended: 'موقوف',
  terminated: 'منتهٍ',
}

export function employeeStatusLabel(status: string): string {
  return STATUS_LABEL[status] ?? status
}

export function employeeStatusTone(status: string): 'green' | 'amber' | 'slate' | 'navy' {
  if (status === 'active') return 'green'
  if (status === 'on_leave') return 'amber'
  if (status === 'terminated') return 'slate'
  return 'navy' // suspended
}

/** عنصر قائمة الموظفين — مخطّط غير صارم (يتجاهل الحقول الإضافية من toArray). */
export const employeeListItemSchema = z.object({
  id: z.number(),
  employee_no: z.string(),
  full_name_ar: z.string(),
  job_title: z.string().nullable().optional(),
  status: z.string(),
  department: z.object({ id: z.number(), name: z.string() }).nullable().optional(),
})
export type EmployeeListItem = z.infer<typeof employeeListItemSchema>

const paginationMetaSchema = z.object({
  page: z.number(),
  per_page: z.number(),
  total: z.number(),
  total_pages: z.number(),
})
export type PaginationMeta = z.infer<typeof paginationMetaSchema>

export interface EmployeeListParams {
  search?: string
  status?: string
  page?: number
  perPage?: number
}

function buildQuery(params: EmployeeListParams): string {
  const q = new URLSearchParams()
  if (params.search) q.set('search', params.search)
  if (params.status) q.set('status', params.status)
  q.set('page', String(params.page ?? 1))
  q.set('per_page', String(params.perPage ?? 15))
  return q.toString()
}

export interface EmployeeListResult {
  items: EmployeeListItem[]
  meta: PaginationMeta
}

/** قائمة الموظفين — `GET /employees` (يحرسها الخادم بصلاحية employees.view). */
export async function fetchEmployees(params: EmployeeListParams = {}): Promise<EmployeeListResult> {
  const env = await api.getPage<unknown>(`employees?${buildQuery(params)}`)
  return {
    items: z.array(employeeListItemSchema).parse(env.data ?? []),
    meta: paginationMetaSchema.parse(env.meta),
  }
}

/** تعطيل موظف (أرشفة ناعمة) — `DELETE /employees/{id}` (يحرسها employees.delete). */
export function deactivateEmployee(id: number): Promise<unknown> {
  return apiRequest<unknown>(`employees/${id}`, { method: 'DELETE' })
}
