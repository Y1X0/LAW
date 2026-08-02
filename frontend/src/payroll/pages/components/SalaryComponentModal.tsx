import { useState, type FormEvent } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Button, Field, SelectField } from '@/core/ui/primitives'
import { useToast } from '@/core/ui/useToast'
import { Modal } from '@/admin/ui/Modal'
import { ApiError } from '@/core/api/types'
import {
  COMPONENT_TYPES,
  VALUE_TYPES,
  type SalaryComponent,
  componentTypeLabel,
  createSalaryComponent,
  updateSalaryComponent,
  valueTypeLabel,
} from '@/payroll/api/salaryComponents'

/**
 * نموذج إنشاء/تعديل مكوّن راتب (Phase 2 / PR-3) — POST/PUT الموجودان فقط.
 * التعطيل يتم عبر حقل «مفعّل» (is_active) في التحديث — لا حذف نهائي (لا endpoint).
 */
export function SalaryComponentModal({ component, onClose }: { component?: SalaryComponent; onClose: () => void }) {
  const qc = useQueryClient()
  const { show } = useToast()
  const editing = !!component
  const [name, setName] = useState(component?.name ?? '')
  const [code, setCode] = useState(component?.code ?? '')
  const [type, setType] = useState(component?.type ?? 'allowance')
  const [valueType, setValueType] = useState(component?.value_type ?? 'fixed')
  const [isActive, setIsActive] = useState(component?.is_active ?? true)

  const save = useMutation({
    mutationFn: () => {
      const payload = { name: name.trim(), code: code.trim(), type, value_type: valueType, is_active: isActive }
      return editing ? updateSalaryComponent(component!.id, payload) : createSalaryComponent(payload)
    },
    onSuccess: () => {
      show(editing ? 'تم تحديث المكوّن' : 'تم إنشاء المكوّن')
      void qc.invalidateQueries({ queryKey: ['payroll', 'components'] })
      onClose()
    },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّر حفظ المكوّن', 'error'),
  })

  function onSubmit(e: FormEvent) {
    e.preventDefault()
    save.mutate()
  }

  return (
    <Modal title={editing ? 'تعديل مكوّن راتب' : 'إنشاء مكوّن راتب'} onClose={onClose}>
      <form onSubmit={onSubmit} className="space-y-3">
        <Field label="الاسم *" value={name} onChange={(e) => setName(e.target.value)} required />
        <Field label="الرمز *" value={code} onChange={(e) => setCode(e.target.value)} required placeholder="مثال: HOUSING" />
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <SelectField label="النوع *" value={type} onChange={(e) => setType(e.target.value)}>
            {COMPONENT_TYPES.map((t) => <option key={t} value={t}>{componentTypeLabel(t)}</option>)}
          </SelectField>
          <SelectField label="نوع القيمة" value={valueType} onChange={(e) => setValueType(e.target.value)}>
            {VALUE_TYPES.map((v) => <option key={v} value={v}>{valueTypeLabel(v)}</option>)}
          </SelectField>
        </div>
        <label className="flex items-center gap-2 text-sm text-slate-700">
          <input type="checkbox" checked={isActive} onChange={(e) => setIsActive(e.target.checked)} className="h-4 w-4 rounded border-slate-300" />
          مفعّل
        </label>
        <div className="flex justify-end gap-2 pt-1">
          <Button type="button" variant="ghost" onClick={onClose} disabled={save.isPending}>إلغاء</Button>
          <Button type="submit" disabled={save.isPending}>{save.isPending ? 'جارٍ…' : editing ? 'حفظ' : 'إنشاء'}</Button>
        </div>
      </form>
    </Modal>
  )
}
