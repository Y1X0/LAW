import { z } from 'zod'
import { api, apiRequest } from '@/core/api/client'

/** صلاحية من الكتالوج. */
export const permissionSchema = z.object({
  id: z.number(),
  name: z.string(),
  module: z.string().nullable().optional(),
  description: z.string().nullable().optional(),
})
export type Permission = z.infer<typeof permissionSchema>

/** دور مع صلاحياته (من `GET /roles`). */
export const roleSchema = z.object({
  id: z.number(),
  name: z.string(),
  display_name: z.string().nullable().optional(),
  is_system: z.boolean().optional().default(false),
  permissions: z.array(permissionSchema).default([]),
})
export type Role = z.infer<typeof roleSchema>

/** قائمة الأدوار — `GET /roles` (يحرسها الخادم بصلاحية roles.manage). */
export async function fetchRoles(): Promise<Role[]> {
  return z.array(roleSchema).parse(await api.get<unknown>('roles'))
}

/** كتالوج الصلاحيات — `GET /permissions`. */
export async function fetchPermissions(): Promise<Permission[]> {
  return z.array(permissionSchema).parse(await api.get<unknown>('permissions'))
}

/** إنشاء دور — `POST /roles`. */
export async function createRole(input: { name: string; display_name: string }): Promise<Role> {
  return roleSchema.parse(await api.post<unknown>('roles', input))
}

/** تعديل دور (الاسم الظاهر) — `PUT /roles/{id}`. */
export async function updateRole(id: number, input: { display_name?: string; name?: string }): Promise<Role> {
  return roleSchema.parse(await apiRequest<unknown>(`roles/${id}`, { method: 'PUT', body: input }))
}

/** حذف دور — `DELETE /roles/{id}` (الأدوار النظامية وآخر مدير محميّة في الخادم). */
export function deleteRole(id: number): Promise<unknown> {
  return apiRequest<unknown>(`roles/${id}`, { method: 'DELETE' })
}

/** مزامنة صلاحيات دور — `PUT /roles/{id}/permissions`. */
export async function syncRolePermissions(id: number, permissionIds: number[]): Promise<Role> {
  return roleSchema.parse(
    await apiRequest<unknown>(`roles/${id}/permissions`, { method: 'PUT', body: { permissions: permissionIds } }),
  )
}

/**
 * نسخ دور — عملية على العميل تعيد استخدام النقاط القائمة (بلا نقطة جديدة):
 * تنشئ دوراً جديداً ثم تزامن صلاحيات الأصل عليه.
 */
export async function copyRole(source: Role, input: { name: string; display_name: string }): Promise<Role> {
  const created = await createRole(input)
  if (source.permissions.length > 0) {
    return syncRolePermissions(
      created.id,
      source.permissions.map((p) => p.id),
    )
  }
  return created
}

// ---- إسناد أدوار المستخدمين (نقاط قائمة تحت users.manage) ----

const userRoleSchema = z.object({
  id: z.number(),
  name: z.string(),
  display_name: z.string().nullable().optional(),
})
export type UserRole = z.infer<typeof userRoleSchema>

/** أدوار مستخدم — `GET /users/{id}/roles`. */
export async function fetchUserRoles(userId: number): Promise<UserRole[]> {
  return z.array(userRoleSchema).parse(await api.get<unknown>(`users/${userId}/roles`))
}

/** إسناد دور لمستخدم — `POST /users/{id}/roles`. */
export function assignUserRole(userId: number, roleId: number): Promise<unknown> {
  return api.post<unknown>(`users/${userId}/roles`, { role_id: roleId })
}

/** إزالة دور عن مستخدم — `DELETE /users/{id}/roles/{roleId}` (آخر مدير محميّ في الخادم). */
export function removeUserRole(userId: number, roleId: number): Promise<unknown> {
  return apiRequest<unknown>(`users/${userId}/roles/${roleId}`, { method: 'DELETE' })
}

/** تجميع الصلاحيات حسب الوحدة (module) للعرض في المصفوفة. */
export function groupByModule(permissions: Permission[]): Record<string, Permission[]> {
  const groups: Record<string, Permission[]> = {}
  for (const p of permissions) {
    const key = p.module || 'أخرى'
    ;(groups[key] ??= []).push(p)
  }
  return groups
}
