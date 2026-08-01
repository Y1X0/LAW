import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Button } from '@/core/ui/primitives'
import { ErrorState, Skeleton } from '@/core/ui/states'
import { useToast } from '@/core/ui/useToast'
import { Modal } from '@/admin/ui/Modal'
import { assignUserRole, fetchRoles, fetchUserRoles, removeUserRole } from '@/admin/api/roles'
import { type AdminUser } from '@/admin/api/users'
import { ApiError } from '@/core/api/types'

/**
 * إسناد/إزالة أدوار مستخدم (ADMIN-3) — تعيد استخدام نقاط `/users/{id}/roles` القائمة.
 * إزالة آخر دور يمنح users.manage عن آخر مدير يمنعها الخادم (LAST_ADMIN_PROTECTED).
 */
export function ManageUserRolesModal({ user, onClose }: { user: AdminUser; onClose: () => void }) {
  const qc = useQueryClient()
  const { show } = useToast()

  const allRoles = useQuery({ queryKey: ['admin', 'roles'], queryFn: fetchRoles })
  const userRoles = useQuery({ queryKey: ['admin', 'user-roles', user.id], queryFn: () => fetchUserRoles(user.id) })

  const assignedIds = new Set((userRoles.data ?? []).map((r) => r.id))

  const invalidate = () => {
    void qc.invalidateQueries({ queryKey: ['admin', 'user-roles', user.id] })
    void qc.invalidateQueries({ queryKey: ['admin', 'users'] })
  }

  const toggle = useMutation({
    mutationFn: ({ roleId, assigned }: { roleId: number; assigned: boolean }) =>
      assigned ? removeUserRole(user.id, roleId) : assignUserRole(user.id, roleId),
    onSuccess: (_d, v) => {
      show(v.assigned ? 'تمت إزالة الدور' : 'تم إسناد الدور')
      invalidate()
    },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّرت العملية', 'error'),
  })

  const loading = allRoles.isPending || userRoles.isPending

  return (
    <Modal title={`أدوار المستخدم — ${user.name}`} onClose={onClose}>
      {loading ? (
        <div className="space-y-2" data-testid="user-roles-skeleton">
          {Array.from({ length: 4 }).map((_, i) => (
            <Skeleton key={i} className="h-8 w-full" />
          ))}
        </div>
      ) : allRoles.isError || userRoles.isError ? (
        // فشل جلب أدوار المستخدم يجب ألّا يُظهر كل الأدوار كغير مُسندة (إسناد مزدوج).
        <ErrorState error={allRoles.error ?? userRoles.error} />
      ) : (
        <>
          <ul className="max-h-[60vh] divide-y divide-slate-100 overflow-y-auto">
            {allRoles.data?.map((role) => {
              const assigned = assignedIds.has(role.id)
              const busy = toggle.isPending && toggle.variables?.roleId === role.id
              return (
                <li key={role.id} className="flex items-center justify-between gap-2 py-2">
                  <span>
                    <span className="text-sm font-medium text-slate-800">{role.display_name || role.name}</span>
                    <span className="block text-xs text-slate-400">{role.name}</span>
                  </span>
                  <Button
                    variant={assigned ? 'ghost' : 'primary'}
                    onClick={() => toggle.mutate({ roleId: role.id, assigned })}
                    disabled={busy}
                  >
                    {busy ? 'جارٍ…' : assigned ? 'إزالة' : 'إسناد'}
                  </Button>
                </li>
              )
            })}
          </ul>
          <div className="mt-4 flex justify-end">
            <Button type="button" variant="ghost" onClick={onClose}>
              إغلاق
            </Button>
          </div>
        </>
      )}
    </Modal>
  )
}
