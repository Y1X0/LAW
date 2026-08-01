import { useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Badge, Button, Card, Field } from '@/core/ui/primitives'
import { PageHeader } from '@/core/ui/section'
import { EmptyState, ErrorState, Skeleton } from '@/core/ui/states'
import { useToast } from '@/core/ui/useToast'
import { Modal } from '@/admin/ui/Modal'
import { deleteRole, fetchRoles, updateRole, type Role } from '@/admin/api/roles'
import { ApiError } from '@/core/api/types'
import { RoleFormModal } from './components/RoleFormModal'
import { RolePermissionsModal } from './components/RolePermissionsModal'

/**
 * إدارة الأدوار (ADMIN-3) — قائمة `GET /roles`، إنشاء/تعديل/حذف/نسخ، وتحرير
 * مصفوفة صلاحيات كل دور. تعيد استخدام نقاط RBAC القائمة دون أي تغيير لسلوكها.
 */
export function AdminRolesPage() {
  const qc = useQueryClient()
  const { show } = useToast()
  const [creating, setCreating] = useState(false)
  const [copying, setCopying] = useState<Role | null>(null)
  const [editingPerms, setEditingPerms] = useState<Role | null>(null)
  const [renaming, setRenaming] = useState<Role | null>(null)
  const [confirmingId, setConfirmingId] = useState<number | null>(null)

  const { data, isPending, isError, error, refetch } = useQuery({
    queryKey: ['admin', 'roles'],
    queryFn: fetchRoles,
  })

  const remove = useMutation({
    mutationFn: (id: number) => deleteRole(id),
    onSuccess: () => {
      setConfirmingId(null)
      show('تم حذف الدور')
      void qc.invalidateQueries({ queryKey: ['admin', 'roles'] })
    },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّر حذف الدور', 'error'),
  })

  return (
    <div className="space-y-5">
      <PageHeader
        title="الأدوار والصلاحيات"
        subtitle="تعريف الأدوار وربط الصلاحيات بها"
        action={<Button onClick={() => setCreating(true)}>إنشاء دور</Button>}
      />

      {isPending ? (
        <RolesSkeleton />
      ) : isError ? (
        <ErrorState error={error}>
          <div className="mt-3">
            <Button onClick={() => void refetch()}>إعادة المحاولة</Button>
          </div>
        </ErrorState>
      ) : data.length === 0 ? (
        <Card>
          <EmptyState message="لا توجد أدوار بعد." />
        </Card>
      ) : (
        <Card className="lp-table-wrap p-0">
          <table className="lp-table min-w-[760px] text-right text-sm">
            <thead>
              <tr className="text-xs">
                <th className="px-4 py-3 text-right">الدور</th>
                <th className="px-4 py-3 text-right">المعرّف</th>
                <th className="px-4 py-3 text-right">الصلاحيات</th>
                <th className="px-4 py-3 text-right">إجراءات</th>
              </tr>
            </thead>
            <tbody>
              {data.map((role) => (
                <tr key={role.id} className="border-b border-slate-100 last:border-0">
                  <td className="px-4 py-3">
                    <span className="font-medium text-slate-800">{role.display_name || role.name}</span>
                    {role.is_system && (
                      <span className="ms-2 align-middle">
                        <Badge tone="navy">نظامي</Badge>
                      </span>
                    )}
                  </td>
                  <td className="px-4 py-3 tabular-nums text-xs text-slate-400">{role.name}</td>
                  <td className="px-4 py-3 tabular-nums text-slate-600">{role.permissions.length}</td>
                  <td className="px-4 py-3">
                    <div className="flex flex-wrap items-center gap-1.5">
                      <Button variant="ghost" onClick={() => setEditingPerms(role)}>
                        الصلاحيات
                      </Button>
                      <Button variant="ghost" onClick={() => setRenaming(role)}>
                        تعديل
                      </Button>
                      <Button variant="ghost" onClick={() => setCopying(role)}>
                        نسخ
                      </Button>
                      {!role.is_system &&
                        (confirmingId === role.id ? (
                          <span className="flex items-center gap-1.5">
                            <Button
                              onClick={() => remove.mutate(role.id)}
                              disabled={remove.isPending && remove.variables === role.id}
                            >
                              تأكيد
                            </Button>
                            <Button variant="ghost" onClick={() => setConfirmingId(null)}>
                              إلغاء
                            </Button>
                          </span>
                        ) : (
                          <Button variant="ghost" onClick={() => setConfirmingId(role.id)}>
                            حذف
                          </Button>
                        ))}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      )}

      {creating && <RoleFormModal onClose={() => setCreating(false)} />}
      {copying && <RoleFormModal copyFrom={copying} onClose={() => setCopying(null)} />}
      {editingPerms && <RolePermissionsModal role={editingPerms} onClose={() => setEditingPerms(null)} />}
      {renaming && <RenameRoleModal role={renaming} onClose={() => setRenaming(null)} />}
    </div>
  )
}

/** تعديل الاسم الظاهر للدور — `PUT /roles/{id}`. */
function RenameRoleModal({ role, onClose }: { role: Role; onClose: () => void }) {
  const qc = useQueryClient()
  const { show } = useToast()
  const [displayName, setDisplayName] = useState(role.display_name || '')

  const save = useMutation({
    mutationFn: () => updateRole(role.id, { display_name: displayName.trim() }),
    onSuccess: () => {
      show('تم تحديث الدور')
      void qc.invalidateQueries({ queryKey: ['admin', 'roles'] })
      onClose()
    },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّر تحديث الدور', 'error'),
  })

  function onSubmit(e: FormEvent) {
    e.preventDefault()
    save.mutate()
  }

  return (
    <Modal title={`تعديل الدور — ${role.name}`} onClose={onClose}>
      <form onSubmit={onSubmit} className="space-y-3">
        <Field label="الاسم الظاهر" value={displayName} onChange={(e) => setDisplayName(e.target.value)} required />
        <div className="flex justify-end gap-2 pt-1">
          <Button type="button" variant="ghost" onClick={onClose} disabled={save.isPending}>
            إلغاء
          </Button>
          <Button type="submit" disabled={save.isPending}>
            {save.isPending ? 'جارٍ…' : 'حفظ'}
          </Button>
        </div>
      </form>
    </Modal>
  )
}

function RolesSkeleton() {
  return (
    <Card>
      <div data-testid="roles-skeleton" className="space-y-3">
        {Array.from({ length: 5 }).map((_, i) => (
          <Skeleton key={i} className="h-9 w-full" />
        ))}
      </div>
    </Card>
  )
}
