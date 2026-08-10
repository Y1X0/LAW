import { useState, type FormEvent } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Button, Field, SelectField } from '@/core/ui/primitives'
import { useToast } from '@/core/ui/useToast'
import { useFormErrors } from '@/core/api/useFormErrors'
import { Modal } from '@/admin/ui/Modal'
import { PAYMENT_METHODS, paymentMethodLabel, setSalaryProfile } from '@/payroll/api/employeeSalary'

/**
 * ضبط ملف راتب أساسي جديد للموظف (Phase 2 / PR-3b) — POST الموجود.
 * الضبط الجديد يؤرشف السابق تلقائياً (الخادم)، فلا حذف/تعديل تاريخي هنا.
 */
export function SetSalaryProfileModal({ employeeId, onClose }: { employeeId: number; onClose: () => void }) {
  const qc = useQueryClient()
  const { show } = useToast()
  const formErrors = useFormErrors()
  const [basic, setBasic] = useState('')
  const [currency, setCurrency] = useState('SAR')
  const [method, setMethod] = useState('bank')
  const [from, setFrom] = useState('')

  const save = useMutation({
    mutationFn: () =>
      setSalaryProfile(employeeId, {
        basic_salary: Number(basic),
        currency: currency.trim() || undefined,
        payment_method: method,
        effective_from: from || undefined,
      }),
    onSuccess: () => {
      show('تم تحديث ملف الراتب')
      void qc.invalidateQueries({ queryKey: ['payroll', 'salary-profiles', employeeId] })
      onClose()
    },
    onError: formErrors.onError,
  })

  function onSubmit(e: FormEvent) {
    e.preventDefault()
    formErrors.reset()
    save.mutate()
  }

  return (
    <Modal title="تحديث الراتب الأساسي" onClose={onClose}>
      <form onSubmit={onSubmit} className="space-y-3">
        <Field label="الراتب الأساسي *" type="number" value={basic} onChange={(e) => setBasic(e.target.value)} min={0} step="0.01" required error={formErrors.fieldError('basic_salary')} />
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <Field label="العملة" value={currency} onChange={(e) => setCurrency(e.target.value)} maxLength={3} error={formErrors.fieldError('currency')} />
          <SelectField label="طريقة الدفع" value={method} onChange={(e) => setMethod(e.target.value)} error={formErrors.fieldError('payment_method')}>
            {PAYMENT_METHODS.map((m) => <option key={m} value={m}>{paymentMethodLabel(m)}</option>)}
          </SelectField>
        </div>
        <Field label="ساري من (اختياري)" type="date" value={from} onChange={(e) => setFrom(e.target.value)} error={formErrors.fieldError('effective_from')} />
        <p className="text-xs text-slate-400">حفظ ملف جديد يؤرشف الملف السابق ويصبح هو النشط.</p>
        <div className="flex justify-end gap-2 pt-1">
          <Button type="button" variant="ghost" onClick={onClose} disabled={save.isPending}>إلغاء</Button>
          <Button type="submit" disabled={save.isPending}>{save.isPending ? 'جارٍ…' : 'حفظ'}</Button>
        </div>
      </form>
    </Modal>
  )
}
