import { z } from 'zod'
import { api, apiRequest } from '@/core/api/client'

/** فرع تنظيمي — من `GET /branches` (يحرسها الخادم بصلاحية org.manage). */
export const branchSchema = z.object({
  id: z.number(),
  name: z.string(),
  code: z.string(),
  city: z.string().nullable().optional(),
  phone: z.string().nullable().optional(),
  address: z.string().nullable().optional(),
  is_active: z.boolean().optional().default(true),
  departments_count: z.number().optional().default(0),
})
export type Branch = z.infer<typeof branchSchema>

/** قسم ضمن فرع — من `GET /departments`. */
export const departmentSchema = z.object({
  id: z.number(),
  branch_id: z.number(),
  name: z.string(),
  is_active: z.boolean().optional().default(true),
  branch: z.object({ id: z.number(), name: z.string() }).nullable().optional(),
})
export type Department = z.infer<typeof departmentSchema>

export type BranchInput = {
  name: string
  code: string
  city?: string
  phone?: string
  address?: string
  is_active?: boolean
}

export type DepartmentInput = {
  branch_id: number
  name: string
  is_active?: boolean
}

export async function fetchBranches(): Promise<Branch[]> {
  return z.array(branchSchema).parse(await api.get<unknown>('branches'))
}

export async function createBranch(input: BranchInput): Promise<Branch> {
  return branchSchema.parse(await api.post<unknown>('branches', input))
}

export async function updateBranch(id: number, input: Partial<BranchInput>): Promise<Branch> {
  return branchSchema.parse(await apiRequest<unknown>(`branches/${id}`, { method: 'PUT', body: input }))
}

export async function deleteBranch(id: number): Promise<void> {
  await apiRequest<unknown>(`branches/${id}`, { method: 'DELETE' })
}

export async function fetchDepartments(branchId?: number): Promise<Department[]> {
  const q = branchId ? `?branch_id=${branchId}` : ''
  return z.array(departmentSchema).parse(await api.get<unknown>(`departments${q}`))
}

export async function createDepartment(input: DepartmentInput): Promise<Department> {
  return departmentSchema.parse(await api.post<unknown>('departments', input))
}

export async function updateDepartment(id: number, input: Partial<DepartmentInput>): Promise<Department> {
  return departmentSchema.parse(await apiRequest<unknown>(`departments/${id}`, { method: 'PUT', body: input }))
}

export async function deleteDepartment(id: number): Promise<void> {
  await apiRequest<unknown>(`departments/${id}`, { method: 'DELETE' })
}
