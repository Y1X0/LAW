import { z } from 'zod'
import { api, apiRequest } from '@/core/api/client'

/**
 * الحقول المخصّصة (Phase 12) — طبقة API لبنّاء التعريفات الإداري. الواجهة لا تُثبّت الأنواع
 * ولا الأدوار ولا الكيانات؛ كلها تأتي من نقطة meta (مصدر الخادم) فيظهر أي جديد بلا تعديل كود.
 */
const customFieldSchema = z.object({
  id: z.number(),
  entity: z.string(),
  key: z.string(),
  label: z.string(),
  description: z.string().nullable().optional(),
  type: z.string(),
  required: z.boolean(),
  options: z.array(z.string()).nullable().optional(),
  default_value: z.string().nullable().optional(),
  display_in: z.array(z.string()).nullable().optional(),
  view_roles: z.array(z.string()).nullable().optional(),
  edit_roles: z.array(z.string()).nullable().optional(),
  search_roles: z.array(z.string()).nullable().optional(),
  export_roles: z.array(z.string()).nullable().optional(),
  sort_order: z.number(),
  is_active: z.boolean(),
})
export type CustomField = z.infer<typeof customFieldSchema>

const labeled = z.object({ key: z.string(), label: z.string() })
const roleMeta = z.object({ id: z.string(), name: z.string() })

/** بيانات بناء الواجهة من الخادم: الكيانات المعروضة، الأنواع، سياقات العرض، الأدوار. */
const metaSchema = z.object({
  entities: z.array(labeled),
  types: z.array(labeled),
  contexts: z.array(labeled),
  roles: z.array(roleMeta),
})
export type CustomFieldMeta = z.infer<typeof metaSchema>
export type LabeledOption = z.infer<typeof labeled>
export type RoleOption = z.infer<typeof roleMeta>

/** الإجراءات الأربعة لصلاحيات كل حقل — تُرسم كمصفوفة (دور × إجراء). */
export const ROLE_ACTIONS = [
  { key: 'view_roles', label: 'عرض' },
  { key: 'edit_roles', label: 'تعديل' },
  { key: 'search_roles', label: 'بحث' },
  { key: 'export_roles', label: 'تصدير' },
] as const
export type RoleAction = (typeof ROLE_ACTIONS)[number]['key']

export interface CustomFieldInput {
  entity: string
  key: string
  label: string
  description?: string
  type: string
  required: boolean
  options?: string[]
  default_value?: string
  display_in: string[]
  view_roles: string[]
  edit_roles: string[]
  search_roles: string[]
  export_roles: string[]
  sort_order: number
  is_active: boolean
}

export async function fetchCustomFieldMeta(): Promise<CustomFieldMeta> {
  return metaSchema.parse(await api.get<unknown>('admin/custom-fields/meta'))
}

export async function fetchCustomFields(entity: string): Promise<CustomField[]> {
  return z.array(customFieldSchema).parse(await api.get<unknown>(`admin/custom-fields?entity=${encodeURIComponent(entity)}`))
}

export async function createCustomField(input: CustomFieldInput): Promise<CustomField> {
  return customFieldSchema.parse(await api.post<unknown>('admin/custom-fields', input))
}

export async function updateCustomField(id: number, input: Partial<CustomFieldInput>): Promise<CustomField> {
  return customFieldSchema.parse(await apiRequest<unknown>(`admin/custom-fields/${id}`, { method: 'PATCH', body: input }))
}

export function deleteCustomField(id: number): Promise<unknown> {
  return apiRequest<unknown>(`admin/custom-fields/${id}`, { method: 'DELETE' })
}
