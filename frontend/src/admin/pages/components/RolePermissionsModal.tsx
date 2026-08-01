import { useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Button } from '@/core/ui/primitives'
import { ErrorState, Skeleton } from '@/core/ui/states'
import { useToast } from '@/core/ui/useToast'
import { Modal } from '@/admin/ui/Modal'
import { fetchPermissions, groupByModule, syncRolePermissions, type Role } from '@/admin/api/roles'
import { ApiError } from '@/core/api/types'

/**
 * مصفوفة صلاحيات الدور (ADMIN-3): كل الصلاحيات مجمّعة حسب الوحدة مع تحديد
 * ما يملكه الدور، وحفظها عبر `PUT /roles/{id}/permissions` القائمة.
 */
export function RolePermissionsModal({ role, onClose }: { role: Role; onClose: () => void }) {
  const qc = useQueryClient()
  const { show } = useToast()
  const [selected, setSelected] = useState<Set<number>>(new Set(role.permissions.map((p) => p.id)))

  const perms = useQuery({ queryKey: ['admin', 'permissions'], queryFn: fetchPermissions })

  const grouped = useMemo(() => (perms.data ? groupByModule(perms.data) : {}), [perms.data])

  const save = useMutation({
    mutationFn: () => syncRolePermissions(role.id, Array.from(selected)),
    onSuccess: () => {
      show('تم حفظ صلاحيات الدور')
      void qc.invalidateQueries({ queryKey: ['admin', 'roles'] })
      onClose()
    },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّر حفظ الصلاحيات', 'error'),
  })

  function toggle(id: number) {
    setSelected((prev) => {
      const next = new Set(prev)
      if (next.has(id)) next.delete(id)
      else next.add(id)
      return next
    })
  }

  return (
    <Modal title={`صلاحيات الدور — ${role.display_name || role.name}`} onClose={onClose}>
      {perms.isPending ? (
        <div className="space-y-2" data-testid="perms-skeleton">
          {Array.from({ length: 6 }).map((_, i) => (
            <Skeleton key={i} className="h-6 w-full" />
          ))}
        </div>
      ) : perms.isError ? (
        <ErrorState error={perms.error} />
      ) : (
        <>
          <div className="max-h-[60vh] space-y-4 overflow-y-auto pe-1">
            {Object.entries(grouped).map(([module, list]) => (
              <fieldset key={module} className="rounded-lg border border-slate-200 p-3">
                <legend className="px-1 text-xs font-semibold text-brand-700">{module}</legend>
                <div className="grid grid-cols-1 gap-1.5 sm:grid-cols-2">
                  {list.map((p) => (
                    <label key={p.id} className="flex items-center gap-2 text-sm text-slate-700">
                      <input
                        type="checkbox"
                        className="h-4 w-4 accent-brand-600"
                        checked={selected.has(p.id)}
                        onChange={() => toggle(p.id)}
                      />
                      <span className="truncate" title={p.description ?? p.name}>
                        {p.name}
                      </span>
                    </label>
                  ))}
                </div>
              </fieldset>
            ))}
          </div>
          <div className="mt-4 flex items-center justify-between gap-2">
            <span className="text-xs text-slate-400 tabular-nums">{selected.size} صلاحية محدّدة</span>
            <div className="flex gap-2">
              <Button type="button" variant="ghost" onClick={onClose} disabled={save.isPending}>
                إلغاء
              </Button>
              <Button type="button" onClick={() => save.mutate()} disabled={save.isPending}>
                {save.isPending ? 'جارٍ…' : 'حفظ'}
              </Button>
            </div>
          </div>
        </>
      )}
    </Modal>
  )
}
