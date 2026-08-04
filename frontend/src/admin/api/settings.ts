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

/**
 * أقسام الإعدادات المعروضة (group → title + fields). مطابقة لقائمة السماح على الخادم
 * (SettingsController::ALLOWED). إضافة قسم/حقل هنا وفي الخادم فقط — لا تغيير للنموذج/القاعدة.
 */
export const SETTINGS_GROUPS = [
  {
    group: 'general',
    title: 'عام',
    fields: [
      { key: 'org_name_ar', label: 'اسم المكتب (عربي)' },
      { key: 'org_name_en', label: 'اسم المكتب (إنجليزي)' },
      { key: 'contact_email', label: 'البريد الإلكتروني' },
      { key: 'contact_phone', label: 'الهاتف' },
      { key: 'address', label: 'العنوان' },
      { key: 'website', label: 'الموقع الإلكتروني' },
    ],
  },
  {
    group: 'identity',
    title: 'بيانات المكتب الرسمية',
    fields: [{ key: 'commercial_register', label: 'رقم السجل التجاري' }],
  },
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

/** يسطّح قيم كل المجموعات كخريطة key→string للنموذج (المفاتيح فريدة عبر المجموعات). */
export function settingsValues(settings: Settings): Record<string, string> {
  const out: Record<string, string> = {}
  for (const items of Object.values(settings)) {
    for (const item of items) {
      out[item.key] = typeof item.value === 'string' ? item.value : item.value == null ? '' : String(item.value)
    }
  }
  return out
}
