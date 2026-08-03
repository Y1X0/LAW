import { z } from 'zod'
import { api, apiRequest } from '@/core/api/client'

/* ============================================================
   راتب الموظف (Phase 2 / PR-3b) — ملف الراتب الأساسي + إسناد المكوّنات،
   من نقاط النهاية الموجودة فقط. لا حساب/تشغيل/اعتماد. لا منطق جديد.
   ============================================================ */

const money = z.union([z.string(), z.number(), z.null()]).optional().transform((v) => (v == null || v === '' ? 0 : Number(v)))

export const PAYMENT_METHODS = ['bank', 'cash', 'cheque'] as const
const PAYMENT_METHOD_LABEL: Record<string, string> = { bank: 'تحويل بنكي', cash: 'نقدي', cheque: 'شيك' }
export function paymentMethodLabel(m: string | null | undefined): string {
  return m ? (PAYMENT_METHOD_LABEL[m] ?? m) : '—'
}

// ---- ملف الراتب (basic) ----
export const salaryProfileSchema = z.object({
  id: z.number(),
  basic_salary: money,
  currency: z.string().nullable().optional().transform((v) => v ?? 'SAR'),
  payment_method: z.string().nullable().optional(),
  effective_from: z.string().nullable().optional(),
  effective_to: z.string().nullable().optional(),
  is_active: z.boolean().optional().default(false),
})
export type SalaryProfile = z.infer<typeof salaryProfileSchema>

export async function fetchSalaryProfiles(employeeId: number): Promise<SalaryProfile[]> {
  return z.array(salaryProfileSchema).parse(await api.get<unknown>(`employees/${employeeId}/salary-profiles`))
}

export interface SalaryProfileInput {
  basic_salary: number
  currency?: string
  payment_method?: string
  effective_from?: string
}

/** ضبط ملف راتب نشط جديد (يؤرشف السابق) — `POST /employees/{id}/salary-profiles`. */
export async function setSalaryProfile(employeeId: number, input: SalaryProfileInput): Promise<SalaryProfile> {
  return salaryProfileSchema.parse(await api.post<unknown>(`employees/${employeeId}/salary-profiles`, input))
}

// ---- إسناد المكوّنات للموظف ----
export const employeeComponentSchema = z.object({
  id: z.number(),
  salary_component_id: z.number(),
  value: money,
  effective_from: z.string().nullable().optional(),
  is_active: z.boolean().optional().default(true),
  component: z
    .object({ id: z.number(), name: z.string(), code: z.string(), type: z.string(), value_type: z.string().nullable().optional() })
    .nullable()
    .optional(),
})
export type EmployeeComponent = z.infer<typeof employeeComponentSchema>

export async function fetchEmployeeComponents(employeeId: number): Promise<EmployeeComponent[]> {
  return z.array(employeeComponentSchema).parse(await api.get<unknown>(`employees/${employeeId}/salary-components`))
}

export interface AssignComponentInput {
  salary_component_id: number
  value: number
  effective_from?: string
}

/** إسناد مكوّن نشط للموظف — `POST /employees/{id}/salary-components`. */
export async function assignComponent(employeeId: number, input: AssignComponentInput): Promise<EmployeeComponent> {
  return employeeComponentSchema.parse(await api.post<unknown>(`employees/${employeeId}/salary-components`, input))
}

/** إيقاف إسناد مكوّن (أرشفة) — `DELETE /employee-salary-components/{id}`. */
export function deactivateEmployeeComponent(assignmentId: number): Promise<unknown> {
  return apiRequest<unknown>(`employee-salary-components/${assignmentId}`, { method: 'DELETE' })
}
