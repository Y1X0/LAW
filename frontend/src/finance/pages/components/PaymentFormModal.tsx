import { useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Modal } from '@/admin/ui/Modal'
import { ApiError } from '@/core/api/types'
import { Button, Field, SelectField, TextareaField } from '@/core/ui/primitives'
import { useToast } from '@/core/ui/useToast'
import {
  fetchFinancialAccounts,
  newIdempotencyKey,
  PAYMENT_METHODS,
  recordPayment,
} from '@/finance/api/payments'

/**
 * تسجيل دفعة على فاتورة (Phase 6 · PR-7). لا تحسب الواجهة شيئاً — ترسل المبلغ فقط
 * والخادم يرحّل القيد ويحدّث الرصيد. Idempotency-Key يُولَّد مرّة لهذه الدفعة (يُعاد
 * استخدامه عند إعادة المحاولة) فلا يتكرّر التحصيل.
 */
export function PaymentFormModal({ invoiceId, onClose }: { invoiceId: number; onClose: () => void }) {
  const qc = useQueryClient()
  const { show } = useToast()
  const accounts = useQuery({ queryKey: ['finance', 'accounts'], queryFn: fetchFinancialAccounts })

  const [idempotencyKey] = useState(() => newIdempotencyKey())
  const [amount, setAmount] = useState('')
  const [accountId, setAccountId] = useState('')
  const [method, setMethod] = useState('cash')
  const [reference, setReference] = useState('')
  const [notes, setNotes] = useState('')

  const canSubmit = Number(amount) > 0 && accountId !== '' && !accounts.isPending

  const save = useMutation({
    mutationFn: () =>
      recordPayment(
        invoiceId,
        {
          amount: Number(amount),
          method,
          account_id: Number(accountId),
          reference: reference.trim() || null,
          notes: notes.trim() || null,
        },
        idempotencyKey,
      ),
    onSuccess: () => {
      show('تم تسجيل الدفعة')
      void qc.invalidateQueries({ queryKey: ['finance', 'invoice-payments', invoiceId] })
      void qc.invalidateQueries({ queryKey: ['finance', 'invoice', invoiceId] })
      void qc.invalidateQueries({ queryKey: ['finance', 'invoices'] })
      void qc.invalidateQueries({ queryKey: ['finance', 'client-summary'] })
      onClose()
    },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّر تسجيل الدفعة', 'error'),
  })

  function onSubmit(e: FormEvent) {
    e.preventDefault()
    if (canSubmit) save.mutate()
  }

  return (
    <Modal title="تسجيل دفعة" onClose={onClose}>
      <form onSubmit={onSubmit} className="space-y-3">
        <Field label="المبلغ *" type="number" min="0" step="0.01" value={amount} onChange={(e) => setAmount(e.target.value)} required />
        <SelectField label="الحساب المستلِم *" value={accountId} onChange={(e) => setAccountId(e.target.value)} required>
          <option value="" disabled>اختر حساباً…</option>
          {(accounts.data ?? []).map((a) => (
            <option key={a.id} value={a.id}>{a.name}</option>
          ))}
        </SelectField>
        <SelectField label="طريقة الدفع *" value={method} onChange={(e) => setMethod(e.target.value)}>
          {PAYMENT_METHODS.map((m) => (
            <option key={m.value} value={m.value}>{m.label}</option>
          ))}
        </SelectField>
        <Field label="مرجع (رقم شيك/تحويل)" value={reference} onChange={(e) => setReference(e.target.value)} />
        <TextareaField label="ملاحظات" value={notes} onChange={(e) => setNotes(e.target.value)} rows={2} />

        <div className="flex justify-end gap-2 pt-1">
          <Button type="button" variant="ghost" onClick={onClose} disabled={save.isPending}>إلغاء</Button>
          <Button type="submit" disabled={!canSubmit || save.isPending}>{save.isPending ? 'جارٍ…' : 'تسجيل'}</Button>
        </div>
      </form>
    </Modal>
  )
}
