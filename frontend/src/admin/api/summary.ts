import { z } from 'zod'
import { api } from '@/core/api/client'

/** إحصاءات لوحة النظام (ADMIN-4) — من `GET /admin/summary` (للقراءة فقط). */
export const adminSummarySchema = z.object({
  users: z.object({
    total: z.number(),
    active: z.number(),
    suspended: z.number(),
    locked: z.number(),
    without_roles: z.number(),
  }),
  employees: z.object({
    total: z.number(),
    active: z.number(),
    on_leave: z.number(),
    suspended: z.number(),
    terminated: z.number(),
  }),
  rbac: z.object({ roles: z.number(), permissions: z.number() }),
  legal: z.object({
    cases_total: z.number(),
    cases_open: z.number(),
    cases_closed: z.number(),
    hearings_upcoming: z.number(),
  }),
  hr: z.object({ leave_pending: z.number() }),
  activity: z.array(
    z.object({
      id: z.number(),
      user_id: z.number().nullable(),
      action: z.string(),
      auditable_type: z.string().nullable().optional(),
      created_at: z.string().nullable().optional(),
    }),
  ),
})
export type AdminSummary = z.infer<typeof adminSummarySchema>

/** لوحة النظام — `GET /admin/summary` (يحرسها الخادم بصلاحية users.manage). */
export async function fetchAdminSummary(): Promise<AdminSummary> {
  return adminSummarySchema.parse(await api.get<unknown>('admin/summary'))
}

/** ترجمة عربية موجزة لأحداث التدقيق الشائعة (يبقى الأصل عند غياب الترجمة). */
const ACTION_LABEL: Record<string, string> = {
  login: 'تسجيل دخول',
  logout: 'تسجيل خروج',
  login_failed: 'محاولة دخول فاشلة',
  user_created: 'إنشاء مستخدم',
  user_updated: 'تعديل مستخدم',
  user_disabled: 'تعطيل مستخدم',
  user_enabled: 'تفعيل مستخدم',
  user_password_reset: 'إعادة تعيين كلمة مرور',
  user_role_assigned: 'إسناد دور',
  user_role_removed: 'إزالة دور',
  role_created: 'إنشاء دور',
  role_updated: 'تعديل دور',
  role_deleted: 'حذف دور',
  role_permissions_synced: 'مزامنة صلاحيات دور',
  employee_identity_linked: 'ربط موظف بحساب',
  employee_identity_unlinked: 'فكّ ربط موظف',
}

export function activityLabel(action: string): string {
  return ACTION_LABEL[action] ?? action
}
