import { useState, type FormEvent } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Button, Field, SelectField, TextareaField } from '@/core/ui/primitives'
import { Modal } from '@/admin/ui/Modal'
import { useToast } from '@/core/ui/useToast'
import { useFormErrors } from '@/core/api/useFormErrors'
import { CLIENT_TYPES, type Client, type ClientInput, clientTypeLabel, createClientFull, updateClient } from '@/legal/api/clients'

/**
 * نموذج عميل كامل (Phase 4 / PR-1) — إنشاء، وقابل لإعادة الاستخدام في التعديل (PR-2).
 * فوق `POST /clients` و`PUT /clients/{id}` الموجودين. الحالة تُدار عبر نقطة مستقلّة.
 */
export function ClientFormModal({ existing, onSaved, onClose }: { existing?: Client; onSaved?: (c: Client) => void; onClose: () => void }) {
  const qc = useQueryClient()
  const { show } = useToast()
  const formErrors = useFormErrors()
  const [name, setName] = useState(existing?.name ?? '')
  const [type, setType] = useState(existing?.type ?? 'individual')
  const [phone, setPhone] = useState(existing?.phone ?? '')
  const [email, setEmail] = useState(existing?.email ?? '')
  const [nationalId, setNationalId] = useState(existing?.national_id ?? '')
  const [address, setAddress] = useState(existing?.address ?? '')
  const [notes, setNotes] = useState(existing?.notes ?? '')

  const save = useMutation({
    mutationFn: () => {
      const input: ClientInput = {
        name: name.trim(),
        type,
        phone: phone.trim() || null,
        email: email.trim() || null,
        national_id: nationalId.trim() || null,
        address: address.trim() || null,
        notes: notes.trim() || null,
      }
      return existing ? updateClient(existing.id, input) : createClientFull(input)
    },
    onSuccess: (c) => {
      show(existing ? 'تم تحديث العميل' : 'تمت إضافة العميل')
      void qc.invalidateQueries({ queryKey: ['legal', 'clients'] })
      if (existing) void qc.invalidateQueries({ queryKey: ['legal', 'client', existing.id] })
      onSaved?.(c)
      onClose()
    },
    onError: formErrors.onError,
  })

  const canSubmit = name.trim().length > 0 && !save.isPending
  function onSubmit(e: FormEvent) { e.preventDefault(); formErrors.reset(); if (canSubmit) save.mutate() }

  return (
    <Modal title={existing ? 'تعديل عميل' : 'إنشاء عميل'} onClose={onClose}>
      <form onSubmit={onSubmit} className="space-y-3">
        <Field label="الاسم *" value={name} onChange={(e) => setName(e.target.value)} required error={formErrors.fieldError('name')} />
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <SelectField label="النوع *" value={type} onChange={(e) => setType(e.target.value)} error={formErrors.fieldError('type')}>
            {CLIENT_TYPES.map((t) => <option key={t} value={t}>{clientTypeLabel(t)}</option>)}
          </SelectField>
          <Field label="الهاتف" value={phone} onChange={(e) => setPhone(e.target.value)} error={formErrors.fieldError('phone')} />
          <Field label="البريد" type="email" value={email} onChange={(e) => setEmail(e.target.value)} error={formErrors.fieldError('email')} />
          <Field label="الهوية / السجل" value={nationalId} onChange={(e) => setNationalId(e.target.value)} error={formErrors.fieldError('national_id')} />
        </div>
        <Field label="العنوان" value={address} onChange={(e) => setAddress(e.target.value)} error={formErrors.fieldError('address')} />
        <TextareaField label="ملاحظات" value={notes} onChange={(e) => setNotes(e.target.value)} rows={2} error={formErrors.fieldError('notes')} />
        <div className="flex justify-end gap-2 pt-1">
          <Button type="button" variant="ghost" onClick={onClose} disabled={save.isPending}>إلغاء</Button>
          <Button type="submit" disabled={!canSubmit}>{save.isPending ? 'جارٍ…' : existing ? 'حفظ' : 'إضافة'}</Button>
        </div>
      </form>
    </Modal>
  )
}
