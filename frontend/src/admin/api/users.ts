import { z } from 'zod'
import { api, apiRequest } from '@/core/api/client'

/** حالات المستخدم المعتمدة (من الباك-إند: عمود status). */
export const USER_STATUSES = ['active', 'suspended', 'locked'] as const

const STATUS_LABEL: Record<string, string> = {
  active: 'نشط',
  suspended: 'موقوف',
  locked: 'مقفل',
}

export function userStatusLabel(status: string): string {
  return STATUS_LABEL[status] ?? status
}

export function userStatusTone(status: string): 'green' | 'amber' | 'slate' | 'navy' {
  if (status === 'active') return 'green'
  if (status === 'suspended') return 'amber'
  return 'slate' // locked
}

const roleSchema = z.object({
  id: z.number(),
  name: z.string(),
  display_name: z.string().nullable().optional(),
})

const linkedEmployeeSchema = z.object({
  id: z.number(),
  employee_no: z.string(),
  full_name_ar: z.string(),
})

/** عنصر/تفاصيل المستخدم — مخطّط غير صارم (يتجاهل حقولاً إضافية). */
export const userSchema = z.object({
  id: z.number(),
  name: z.string(),
  username: z.string().nullable().optional(),
  email: z.string(),
  status: z.string(),
  mfa_enabled: z.boolean().optional(),
  last_login_at: z.string().nullable().optional(),
  created_at: z.string().nullable().optional(),
  roles: z.array(roleSchema).default([]),
  employee: linkedEmployeeSchema.nullable().optional(),
})
export type AdminUser = z.infer<typeof userSchema>

const paginationMetaSchema = z.object({
  page: z.number(),
  per_page: z.number(),
  total: z.number(),
  total_pages: z.number(),
})
export type PaginationMeta = z.infer<typeof paginationMetaSchema>

export interface UserListParams {
  search?: string
  status?: string
  page?: number
  perPage?: number
}

function buildQuery(params: UserListParams): string {
  const q = new URLSearchParams()
  if (params.search) q.set('search', params.search)
  if (params.status) q.set('status', params.status)
  q.set('page', String(params.page ?? 1))
  q.set('per_page', String(params.perPage ?? 15))
  return q.toString()
}

export interface UserListResult {
  items: AdminUser[]
  meta: PaginationMeta
}

/** قائمة المستخدمين — `GET /users` (يحرسها الخادم بصلاحية users.manage). */
export async function fetchUsers(params: UserListParams = {}): Promise<UserListResult> {
  const env = await api.getPage<unknown>(`users?${buildQuery(params)}`)
  return {
    items: z.array(userSchema).parse(env.data ?? []),
    meta: paginationMetaSchema.parse(env.meta),
  }
}

/** تفاصيل مستخدم — `GET /users/{id}`. */
export async function fetchUser(id: number): Promise<AdminUser> {
  return userSchema.parse(await api.get<unknown>(`users/${id}`))
}

export interface CreateUserInput {
  name: string
  email: string
  username?: string
  password: string
  status?: string
  role_ids?: number[]
}

/** إنشاء مستخدم — `POST /users`. */
export async function createUser(input: CreateUserInput): Promise<AdminUser> {
  return userSchema.parse(await api.post<unknown>('users', input))
}

export interface UpdateUserInput {
  name?: string
  email?: string
  username?: string | null
}

/** تعديل بيانات مستخدم — `PATCH /users/{id}`. */
export async function updateUser(id: number, input: UpdateUserInput): Promise<AdminUser> {
  return userSchema.parse(await api.patch<unknown>(`users/${id}`, input))
}

/** تعطيل مستخدم — `POST /users/{id}/disable` (status=suspended + إبطال الجلسات). */
export async function disableUser(id: number): Promise<AdminUser> {
  return userSchema.parse(await api.post<unknown>(`users/${id}/disable`))
}

/** تفعيل مستخدم — `POST /users/{id}/enable` (status=active). */
export async function enableUser(id: number): Promise<AdminUser> {
  return userSchema.parse(await api.post<unknown>(`users/${id}/enable`))
}

/** إعادة تعيين كلمة المرور إدارياً — `POST /users/{id}/reset-password`. */
export function resetUserPassword(id: number, password: string): Promise<unknown> {
  return api.post<unknown>(`users/${id}/reset-password`, {
    password,
    password_confirmation: password,
  })
}

/** ربط موظف بالحساب — `POST /users/{id}/employee`. */
export async function linkEmployee(id: number, employeeId: number): Promise<AdminUser> {
  return userSchema.parse(await api.post<unknown>(`users/${id}/employee`, { employee_id: employeeId }))
}

/** فكّ ربط الموظف — `DELETE /users/{id}/employee`. */
export async function unlinkEmployee(id: number): Promise<AdminUser> {
  return userSchema.parse(await apiRequest<unknown>(`users/${id}/employee`, { method: 'DELETE' }))
}
