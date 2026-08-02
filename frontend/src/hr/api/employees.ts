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
  branchId?: number
  departmentId?: number
  page?: number
  perPage?: number
}

function buildQuery(params: EmployeeListParams): string {
  const q = new URLSearchParams()
  if (params.search) q.set('search', params.search)
  if (params.status) q.set('status', params.status)
  if (params.branchId) q.set('branch_id', String(params.branchId))
  if (params.departmentId) q.set('department_id', String(params.departmentId))
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

/** تفاصيل الموظف الكاملة — `GET /employees/{id}` (مخطّط غير صارم). */
export const employeeDetailSchema = z
  .object({
    id: z.number(),
    branch_id: z.number().nullable().optional(),
    department_id: z.number().nullable().optional(),
    employee_no: z.string(),
    full_name_ar: z.string(),
    full_name_en: z.string().nullable().optional(),
    national_id: z.string().nullable().optional(),
    email: z.string().nullable().optional(),
    phone: z.string().nullable().optional(),
    job_title: z.string().nullable().optional(),
    manager_id: z.number().nullable().optional(),
    hire_date: z.string().nullable().optional(),
    status: z.string(),
  })
  .passthrough()
export type EmployeeDetail = z.infer<typeof employeeDetailSchema>

/** حمولة إنشاء/تعديل الموظف (الحقول المالية تُدار في وحدة الرواتب لاحقاً). */
export interface EmployeeInput {
  branch_id: number
  department_id: number
  employee_no: string
  full_name_ar: string
  full_name_en?: string | null
  national_id: string
  email?: string | null
  phone?: string | null
  job_title?: string | null
  manager_id?: number | null
  hire_date?: string | null
  status?: string
}

export async function fetchEmployee(id: number): Promise<EmployeeDetail> {
  return employeeDetailSchema.parse(await api.get<unknown>(`employees/${id}`))
}

export async function createEmployee(input: EmployeeInput): Promise<EmployeeDetail> {
  return employeeDetailSchema.parse(await api.post<unknown>('employees', input))
}

export async function updateEmployee(id: number, input: Partial<EmployeeInput>): Promise<EmployeeDetail> {
  return employeeDetailSchema.parse(await apiRequest<unknown>(`employees/${id}`, { method: 'PUT', body: input }))
}
