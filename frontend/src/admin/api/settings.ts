import { z } from 'zod'
import { api, apiRequest } from '@/core/api/client'

/** إعداد واحد ضمن مجموعة. */
const settingItemSchema = z.object({
  id: z.number(),
  key: z.string(),
  value: z.unknown(),
})

/** الإعدادات مجمّعة حسب المجموعة (group → items). */
export const settingsSchema = z.record(z.array(settingItemSchema))
export type Settings = z.infer<typeof settingsSchema>

/** الحقول العامّة المعروضة في نموذج «الإعدادات العامّة» (group=general). */
export const GENERAL_FIELDS = [
  { key: 'org_name_ar', label: 'اسم المكتب (عربي)' },
  { key: 'org_name_en', label: 'اسم المكتب (إنجليزي)' },
  { key: 'contact_email', label: 'البريد الإلكتروني' },
  { key: 'contact_phone', label: 'الهاتف' },
] as const

/** الإعدادات العامّة — `GET /admin/settings` (يحرسها settings.manage). */
export async function fetchSettings(): Promise<Settings> {
  return settingsSchema.parse(await api.get<unknown>('admin/settings'))
}

export interface SettingInput {
  group: string
  key: string
  value: unknown
}

/** تحديث دفعة إعدادات — `PUT /admin/settings`. */
export async function updateSettings(settings: SettingInput[]): Promise<Settings> {
  return settingsSchema.parse(await apiRequest<unknown>('admin/settings', { method: 'PUT', body: { settings } }))
}

/** يستخرج قيم مجموعة «general» كخريطة key→string للنموذج. */
export function generalValues(settings: Settings): Record<string, string> {
  const out: Record<string, string> = {}
  for (const item of settings.general ?? []) {
    out[item.key] = typeof item.value === 'string' ? item.value : item.value == null ? '' : String(item.value)
  }
  return out
}
