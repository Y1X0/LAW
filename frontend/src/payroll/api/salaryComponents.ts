import { z } from 'zod'
import { api, apiRequest } from '@/core/api/client'

/* ============================================================
   كتالوج مكوّنات الراتب (Phase 2 / PR-3) — قراءة/إنشاء/تعديل من نقاط النهاية
   الموجودة فقط. لا حذف نهائي (لا endpoint) — التعطيل عبر is_active في التحديث.
   ============================================================ */

export const COMPONENT_TYPES = ['allowance', 'deduction'] as const
export const VALUE_TYPES = ['fixed', 'percentage'] as const

const TYPE_LABEL: Record<string, string> = { allowance: 'بدل', deduction: 'استقطاع' }
export function componentTypeLabel(t: string): string {
  return TYPE_LABEL[t] ?? t
}
export function componentTypeTone(t: string): 'green' | 'amber' | 'slate' | 'navy' {
  return t === 'allowance' ? 'green' : 'amber'
}

const VALUE_TYPE_LABEL: Record<string, string> = { fixed: 'مبلغ ثابت', percentage: 'نسبة مئوية' }
export function valueTypeLabel(v: string): string {
  return VALUE_TYPE_LABEL[v] ?? v
}

export const salaryComponentSchema = z.object({
  id: z.number(),
  name: z.string(),
  code: z.string(),
  type: z.string(),
  value_type: z.string().nullable().optional().transform((v) => v ?? 'fixed'),
  is_active: z.boolean().optional().default(true),
})
export type SalaryComponent = z.infer<typeof salaryComponentSchema>

export interface ComponentFilters {
  type?: string
  value_type?: string
}

/** كتالوج المكوّنات (مصفوفة، غير مرقّمة) — `GET /salary-components` (يحرسها payroll.view). */
export async function fetchSalaryComponents(filters: ComponentFilters = {}): Promise<SalaryComponent[]> {
  const q = new URLSearchParams()
  if (filters.type) q.set('type', filters.type)
  if (filters.value_type) q.set('value_type', filters.value_type)
  const qs = q.toString()
  return z.array(salaryComponentSchema).parse(await api.get<unknown>(`salary-components${qs ? `?${qs}` : ''}`))
}

export interface ComponentInput {
  name: string
  code: string
  type: string
  value_type: string
  is_active: boolean
}

/** إنشاء مكوّن — `POST /salary-components` (يحرسها payroll.create). */
export async function createSalaryComponent(input: ComponentInput): Promise<SalaryComponent> {
  return salaryComponentSchema.parse(await api.post<unknown>('salary-components', input))
}

/** تعديل مكوّن (يشمل تفعيل/تعطيل عبر is_active) — `PUT /salary-components/{id}`. */
export async function updateSalaryComponent(id: number, input: Partial<ComponentInput>): Promise<SalaryComponent> {
  return salaryComponentSchema.parse(await apiRequest<unknown>(`salary-components/${id}`, { method: 'PUT', body: input }))
}
