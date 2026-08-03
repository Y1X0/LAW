import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Button, SelectField } from '@/core/ui/primitives'
import { useToast } from '@/core/ui/useToast'
import { Modal } from '@/admin/ui/Modal'
import { ApiError } from '@/core/api/types'
import { ASSIGNMENT_ROLES, assignLawyer, assignmentRoleLabel } from '@/legal/api/cases'
import { EmployeePicker } from './EmployeePicker'

/**
 * إسناد محامٍ للقضية (Phase 3 / PR-3) — اختيار موظف (عبر EmployeePicker المشترك)
 * + دور (رئيسي/مساند) عبر `POST /cases/{id}/assign` الموجود. يمنع الإرسال المكرّر
 * أثناء الحفظ، ويحدّث عرض القضية بعد النجاح (الإسناد يغيّر من يرى القضية — view_own).
 */
export function AssignLawyerModal({ caseId, onClose }: { caseId: number; onClose: () => void }) {
  const qc = useQueryClient()
  const { show } = useToast()
  const [selected, setSelected] = useState<{ id: number; name: string } | null>(null)
  const [role, setRole] = useState('support')

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
        <EmployeePicker
          label="ابحث عن موظف"
          selected={selected}
          onSelect={setSelected}
          onClear={() => setSelected(null)}
          disabled={assign.isPending}
        />

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
