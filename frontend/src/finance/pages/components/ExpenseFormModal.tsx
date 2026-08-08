import { useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Modal } from '@/admin/ui/Modal'
import { ApiError } from '@/core/api/types'
import { Button, Field, SelectField, TextareaField } from '@/core/ui/primitives'
import { useToast } from '@/core/ui/useToast'
import {
  createExpense,
  EXPENSE_METHODS,
  fetchCaseOptions,
  fetchExpenseCategories,
} from '@/finance/api/expenses'
import { fetchFinancialAccounts } from '@/finance/api/payments'

/**
 * تسجيل سند صرف (Phase 6 · PR-9). لا تحسب الواجهة شيئاً — ترسل المبلغ والتصنيف والحساب
 * فقط؛ الخادم يرحّل القيد. القضية اختيارية (قائمتها فارغة لمن لا يملك وصول القضايا).
 */
export function ExpenseFormModal({ onClose }: { onClose: () => void }) {
  const qc = useQueryClient()
  const { show } = useToast()
  const categories = useQuery({ queryKey: ['finance', 'expense-categories'], queryFn: fetchExpenseCategories })
  const accounts = useQuery({ queryKey: ['finance', 'accounts'], queryFn: fetchFinancialAccounts })
  const cases = useQuery({ queryKey: ['finance', 'case-options'], queryFn: fetchCaseOptions })

  const [categoryId, setCategoryId] = useState('')
  const [caseId, setCaseId] = useState('')
  const [amount, setAmount] = useState('')
  const [accountId, setAccountId] = useState('')
  const [method, setMethod] = useState('cash')
  const [beneficiary, setBeneficiary] = useState('')
  const [description, setDescription] = useState('')

  const canSubmit = categoryId !== '' && Number(amount) > 0 && accountId !== '' && !categories.isPending && !accounts.isPending

  const save = useMutation({
    mutationFn: () =>
      createExpense({
        category_id: Number(categoryId),
        case_id: caseId ? Number(caseId) : null,
        amount: Number(amount),
        method,
        account_id: Number(accountId),
        beneficiary: beneficiary.trim() || null,
        description: description.trim() || null,
      }),
    onSuccess: () => {
      show('تم تسجيل المصروف')
      void qc.invalidateQueries({ queryKey: ['finance', 'expenses'] })
      onClose()
    },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّر تسجيل المصروف', 'error'),
  })

  function onSubmit(e: FormEvent) {
    e.preventDefault()
    if (canSubmit) save.mutate()
  }

  const caseOptions = cases.data ?? []

  return (
    <Modal title="تسجيل مصروف" onClose={onClose}>
      <form onSubmit={onSubmit} className="space-y-3">
        <SelectField label="التصنيف *" value={categoryId} onChange={(e) => setCategoryId(e.target.value)} required>
          <option value="" disabled>اختر تصنيفاً…</option>
          {(categories.data ?? []).map((c) => (
            <option key={c.id} value={c.id}>{c.name}</option>
          ))}
        </SelectField>

        {caseOptions.length > 0 && (
          <SelectField label="القضية (اختياري)" value={caseId} onChange={(e) => setCaseId(e.target.value)}>
            <option value="">بلا قضية</option>
            {caseOptions.map((c) => (
              <option key={c.id} value={c.id}>{c.title}</option>
            ))}
          </SelectField>
        )}

        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <Field label="المبلغ *" type="number" min="0" step="0.01" value={amount} onChange={(e) => setAmount(e.target.value)} required />
          <SelectField label="الحساب الدافع *" value={accountId} onChange={(e) => setAccountId(e.target.value)} required>
            <option value="" disabled>اختر حساباً…</option>
            {(accounts.data ?? []).map((a) => (
              <option key={a.id} value={a.id}>{a.name}</option>
            ))}
          </SelectField>
        </div>

        <SelectField label="طريقة الصرف *" value={method} onChange={(e) => setMethod(e.target.value)}>
          {EXPENSE_METHODS.map((m) => (
            <option key={m.value} value={m.value}>{m.label}</option>
          ))}
        </SelectField>
        <Field label="المستفيد" value={beneficiary} onChange={(e) => setBeneficiary(e.target.value)} />
        <TextareaField label="الوصف" value={description} onChange={(e) => setDescription(e.target.value)} rows={2} />

        <div className="flex justify-end gap-2 pt-1">
          <Button type="button" variant="ghost" onClick={onClose} disabled={save.isPending}>إلغاء</Button>
          <Button type="submit" disabled={!canSubmit || save.isPending}>{save.isPending ? 'جارٍ…' : 'تسجيل'}</Button>
        </div>
      </form>
    </Modal>
  )
}
