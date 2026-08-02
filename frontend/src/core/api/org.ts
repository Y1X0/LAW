import { z } from 'zod'
import { api } from '@/core/api/client'

/**
 * قراءة الهيكل التنظيمي (الفروع/الأقسام) — تُغذّي نماذج الموظفين وأي وحدة تحتاجها.
 * القراءة محروسة بصلاحية org.view على الخادم؛ الكتابة تبقى في admin (org.manage).
 */

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

export const departmentSchema = z.object({
  id: z.number(),
  branch_id: z.number(),
  name: z.string(),
  is_active: z.boolean().optional().default(true),
  branch: z.object({ id: z.number(), name: z.string() }).nullable().optional(),
})
export type Department = z.infer<typeof departmentSchema>

export async function fetchBranches(): Promise<Branch[]> {
  return z.array(branchSchema).parse(await api.get<unknown>('branches'))
}

export async function fetchDepartments(branchId?: number): Promise<Department[]> {
  const q = branchId ? `?branch_id=${branchId}` : ''
  return z.array(departmentSchema).parse(await api.get<unknown>(`departments${q}`))
}
