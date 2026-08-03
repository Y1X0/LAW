import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Button, SelectField } from '@/core/ui/primitives'
import { EmptyState, ErrorState, Skeleton } from '@/core/ui/states'
import { useToast } from '@/core/ui/useToast'
import { Modal } from '@/admin/ui/Modal'
import { ApiError } from '@/core/api/types'
import { fetchEmployees, type EmployeeListItem } from '@/hr/api/employees'
import { ASSIGNMENT_ROLES, assignLawyer, assignmentRoleLabel } from '@/legal/api/cases'

/**
 * إسناد محامٍ للقضية (Phase 3 / PR-3) — اختيار موظف + دور (رئيسي/مساند) عبر
 * `POST /cases/{id}/assign` الموجود. يمنع الإرسال المكرّر أثناء الحفظ، ويحدّث
 * عرض القضية بعد النجاح (الإسناد يغيّر من يرى القضية — view_own).
 */
export function AssignLawyerModal({ caseId, onClose }: { caseId: number; onClose: () => void }) {
  const qc = useQueryClient()
  const { show } = useToast()
  const [search, setSearch] = useState('')
  const [selected, setSelected] = useState<{ id: number; name: string } | null>(null)
  const [role, setRole] = useState('support')

  const results = useQuery({
    queryKey: ['legal', 'assign-emp-search', search],
    queryFn: () => fetchEmployees({ search: search.trim(), perPage: 10 }),
    enabled: !selected && search.trim().length >= 1,
  })

  const assign = useMutation({
    mutationFn: () => assignLawyer(caseId, selected!.id, role),
    onSuccess: () => {
      show('تم إسناد المحامي')
      void qc.invalidateQueries({ queryKey: ['legal', 'case', caseId] })
      onClose()
    },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّر الإسناد', 'error'),
  })

  return (
    <Modal title="إسناد محامٍ" onClose={onClose}>
      <div className="space-y-3">
        {selected ? (
          <div className="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <span className="font-medium text-slate-800">{selected.name}</span>
            <Button variant="ghost" onClick={() => setSelected(null)} disabled={assign.isPending}>تغيير</Button>
          </div>
        ) : (
          <label className="block text-sm font-medium text-slate-700">
            ابحث عن موظف
            <input
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="الاسم أو الرقم الوظيفي"
              className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
            />
          </label>
        )}

        {!selected && search.trim().length >= 1 && (
          results.isPending ? <Skeleton className="h-14 w-full" /> :
          results.isError ? <ErrorState error={results.error} /> :
          results.data.items.length === 0 ? <EmptyState message="لا يوجد موظف مطابق." /> : (
            <ul className="space-y-2">
              {results.data.items.map((e: EmployeeListItem) => (
                <li key={e.id}>
                  <button type="button" onClick={() => setSelected({ id: e.id, name: e.full_name_ar })} className="lp-press flex w-full items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-right hover:bg-brand-50">
                    <span className="font-medium text-slate-800">{e.full_name_ar}</span>
                    <span className="tabular-nums text-xs text-slate-400">{e.employee_no}</span>
                  </button>
                </li>
              ))}
            </ul>
          )
        )}

        <SelectField label="الدور" value={role} onChange={(e) => setRole(e.target.value)}>
          {ASSIGNMENT_ROLES.map((r) => <option key={r} value={r}>{assignmentRoleLabel(r)}</option>)}
        </SelectField>

        <div className="flex justify-end gap-2 pt-1">
          <Button type="button" variant="ghost" onClick={onClose} disabled={assign.isPending}>إلغاء</Button>
          <Button type="button" onClick={() => assign.mutate()} disabled={!selected || assign.isPending}>
            {assign.isPending ? 'جارٍ…' : 'إسناد'}
          </Button>
        </div>
      </div>
    </Modal>
  )
}
