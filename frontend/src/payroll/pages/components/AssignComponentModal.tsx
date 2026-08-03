import { useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Button, Field, SelectField } from '@/core/ui/primitives'
import { useToast } from '@/core/ui/useToast'
import { Modal } from '@/admin/ui/Modal'
import { ApiError } from '@/core/api/types'
import { componentTypeLabel, fetchSalaryComponents } from '@/payroll/api/salaryComponents'
import { assignComponent } from '@/payroll/api/employeeSalary'

/**
 * إسناد مكوّن راتب نشط للموظف (Phase 2 / PR-3b) — POST الموجود.
 * القائمة تُغذّى من كتالوج المكوّنات المفعّلة فقط (الخادم يرفض المعطّلة).
 */
export function AssignComponentModal({ employeeId, onClose }: { employeeId: number; onClose: () => void }) {
  const qc = useQueryClient()
  const { show } = useToast()
  const [componentId, setComponentId] = useState('')
  const [value, setValue] = useState('')
  const [from, setFrom] = useState('')

  const catalog = useQuery({ queryKey: ['payroll', 'components', { type: '', valueType: '' }], queryFn: () => fetchSalaryComponents() })
  const active = (catalog.data ?? []).filter((c) => c.is_active)

  const save = useMutation({
    mutationFn: () =>
      assignComponent(employeeId, {
        salary_component_id: Number(componentId),
        value: Number(value),
        effective_from: from || undefined,
      }),
    onSuccess: () => {
      show('تم إسناد المكوّن')
      void qc.invalidateQueries({ queryKey: ['payroll', 'employee-components', employeeId] })
      onClose()
    },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّر إسناد المكوّن', 'error'),
  })

  function onSubmit(e: FormEvent) {
    e.preventDefault()
    if (!componentId) { show('اختر مكوّناً', 'error'); return }
    save.mutate()
  }

  return (
    <Modal title="إسناد مكوّن للموظف" onClose={onClose}>
      <form onSubmit={onSubmit} className="space-y-3">
        <SelectField label="المكوّن *" value={componentId} onChange={(e) => setComponentId(e.target.value)}>
          <option value="">— اختر مكوّناً —</option>
          {active.map((c) => <option key={c.id} value={c.id}>{c.name} ({componentTypeLabel(c.type)})</option>)}
        </SelectField>
        {catalog.data && active.length === 0 && (
          <p className="text-xs text-amber-600">لا توجد مكوّنات مفعّلة في الكتالوج — أنشئها أولاً من صفحة المكوّنات.</p>
        )}
        <Field label="القيمة *" type="number" value={value} onChange={(e) => setValue(e.target.value)} min={0} step="0.01" required />
        <Field label="ساري من (اختياري)" type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
        <div className="flex justify-end gap-2 pt-1">
          <Button type="button" variant="ghost" onClick={onClose} disabled={save.isPending}>إلغاء</Button>
          <Button type="submit" disabled={save.isPending}>{save.isPending ? 'جارٍ…' : 'إسناد'}</Button>
        </div>
      </form>
    </Modal>
  )
}
