import { api, apiRequest } from '@/core/api/client'
import { type Branch, type Department, branchSchema, departmentSchema, fetchBranches, fetchDepartments } from '@/core/api/org'

/**
 * إدارة الهيكل التنظيمي (org.manage) — الكتابة فقط هنا؛ القراءة مشتركة من core/api/org.
 */

export type { Branch, Department }
export { fetchBranches, fetchDepartments }

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

export async function createBranch(input: BranchInput): Promise<Branch> {
  return branchSchema.parse(await api.post<unknown>('branches', input))
}

export async function updateBranch(id: number, input: Partial<BranchInput>): Promise<Branch> {
  return branchSchema.parse(await apiRequest<unknown>(`branches/${id}`, { method: 'PUT', body: input }))
}

export async function deleteBranch(id: number): Promise<void> {
  await apiRequest<unknown>(`branches/${id}`, { method: 'DELETE' })
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
