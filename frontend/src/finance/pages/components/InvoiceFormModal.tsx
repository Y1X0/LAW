import { useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Modal } from '@/admin/ui/Modal'
import { ApiError } from '@/core/api/types'
import { Button, Field, SelectField, TextareaField } from '@/core/ui/primitives'
import { useToast } from '@/core/ui/useToast'
import {
  createInvoice,
  fetchClientOptions,
  type Invoice,
  type InvoiceInput,
  updateInvoice,
} from '@/finance/api/invoices'

interface ItemRow {
  description: string
  quantity: string
  unit_price: string
  tax_rate: string
}

const EMPTY_ROW: ItemRow = { description: '', quantity: '1', unit_price: '', tax_rate: '15' }

function seedRows(existing?: Invoice): ItemRow[] {
  if (!existing?.items || existing.items.length === 0) return [{ ...EMPTY_ROW }]
  return existing.items.map((it) => ({
    description: it.description,
    quantity: String(it.quantity),
    unit_price: String(it.unit_price),
    tax_rate: String(it.tax_rate),
  }))
}

/**
 * إنشاء/تعديل مسودّة فاتورة (Phase 6 · PR-5). الواجهة تجمع البنود والخصم فقط —
 * لا تحسب أي مبلغ؛ الخادم يحسب الإجماليات وتظهر بعد الحفظ في التفاصيل.
 */
export function InvoiceFormModal({ existing, onClose }: { existing?: Invoice; onClose: () => void }) {
  const qc = useQueryClient()
  const { show } = useToast()
  const clients = useQuery({ queryKey: ['finance', 'client-options'], queryFn: fetchClientOptions })

  const [clientId, setClientId] = useState(existing ? String(existing.client_id) : '')
  const [issueDate, setIssueDate] = useState(existing?.issue_date?.slice(0, 10) ?? '')
  const [dueDate, setDueDate] = useState(existing?.due_date?.slice(0, 10) ?? '')
  const [discount, setDiscount] = useState(existing ? String(existing.discount) : '')
  const [notes, setNotes] = useState(existing?.notes ?? '')
  const [rows, setRows] = useState<ItemRow[]>(() => seedRows(existing))

  function setRow(index: number, patch: Partial<ItemRow>) {
    setRows((prev) => prev.map((r, i) => (i === index ? { ...r, ...patch } : r)))
  }
  function addRow() {
    setRows((prev) => [...prev, { ...EMPTY_ROW }])
  }
  function removeRow(index: number) {
    setRows((prev) => (prev.length <= 1 ? prev : prev.filter((_, i) => i !== index)))
  }

  const rowsValid = rows.every((r) => r.description.trim().length > 0 && Number(r.quantity) > 0 && Number(r.unit_price) >= 0)
  const canSubmit = clientId !== '' && rows.length > 0 && rowsValid && !clients.isPending

  const save = useMutation({
    mutationFn: () => {
      const input: InvoiceInput = {
        client_id: Number(clientId),
        issue_date: issueDate || undefined,
        due_date: dueDate || undefined,
        discount: discount ? Number(discount) : 0,
        notes: notes.trim() || null,
        items: rows.map((r) => ({
          description: r.description.trim(),
          quantity: Number(r.quantity),
          unit_price: Number(r.unit_price),
          tax_rate: r.tax_rate ? Number(r.tax_rate) : 0,
        })),
      }
      return existing ? updateInvoice(existing.id, input) : createInvoice(input)
    },
    onSuccess: (invoice) => {
      show(existing ? 'تم تحديث الفاتورة' : 'تم إنشاء الفاتورة')
      void qc.invalidateQueries({ queryKey: ['finance', 'invoices'] })
      if (existing) void qc.invalidateQueries({ queryKey: ['finance', 'invoice', existing.id] })
      onClose()
      void invoice
    },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّر الحفظ', 'error'),
  })

  function onSubmit(e: FormEvent) {
    e.preventDefault()
    if (canSubmit) save.mutate()
  }

  return (
    <Modal title={existing ? 'تعديل مسودّة فاتورة' : 'إنشاء فاتورة'} onClose={onClose}>
      <form onSubmit={onSubmit} className="space-y-3">
        <SelectField label="العميل *" value={clientId} onChange={(e) => setClientId(e.target.value)} required>
          <option value="" disabled>اختر عميلاً…</option>
          {(clients.data ?? []).map((c) => (
            <option key={c.id} value={c.id}>{c.name}</option>
          ))}
        </SelectField>

        <div className="grid grid-cols-2 gap-3">
          <Field label="تاريخ الإصدار" type="date" value={issueDate} onChange={(e) => setIssueDate(e.target.value)} />
          <Field label="تاريخ الاستحقاق" type="date" value={dueDate} onChange={(e) => setDueDate(e.target.value)} />
        </div>

        <div className="space-y-2">
          <div className="flex items-center justify-between">
            <span className="text-sm font-medium text-slate-700">البنود *</span>
            <Button type="button" variant="ghost" onClick={addRow}>+ بند</Button>
          </div>
          {rows.map((row, i) => (
            <div key={i} className="grid grid-cols-12 items-end gap-2">
              <div className="col-span-5"><Field label="الوصف" value={row.description} onChange={(e) => setRow(i, { description: e.target.value })} /></div>
              <div className="col-span-2"><Field label="الكمية" type="number" min="0" step="0.01" value={row.quantity} onChange={(e) => setRow(i, { quantity: e.target.value })} /></div>
              <div className="col-span-2"><Field label="السعر" type="number" min="0" step="0.01" value={row.unit_price} onChange={(e) => setRow(i, { unit_price: e.target.value })} /></div>
              <div className="col-span-2"><Field label="ضريبة٪" type="number" min="0" max="100" step="0.01" value={row.tax_rate} onChange={(e) => setRow(i, { tax_rate: e.target.value })} /></div>
              <div className="col-span-1">
                <Button type="button" variant="ghost" onClick={() => removeRow(i)} disabled={rows.length <= 1} aria-label="حذف البند">×</Button>
              </div>
            </div>
          ))}
        </div>

        <Field label="خصم (على مستوى الفاتورة)" type="number" min="0" step="0.01" value={discount} onChange={(e) => setDiscount(e.target.value)} />
        <TextareaField label="ملاحظات" value={notes} onChange={(e) => setNotes(e.target.value)} rows={2} />

        <p className="text-xs text-slate-400">تُحتسب الإجماليات (الصافي/الضريبة/الإجمالي) في الخادم وتظهر بعد الحفظ.</p>

        <div className="flex justify-end gap-2 pt-1">
          <Button type="button" variant="ghost" onClick={onClose} disabled={save.isPending}>إلغاء</Button>
          <Button type="submit" disabled={!canSubmit || save.isPending}>{save.isPending ? 'جارٍ…' : existing ? 'حفظ' : 'إنشاء'}</Button>
        </div>
      </form>
    </Modal>
  )
}
