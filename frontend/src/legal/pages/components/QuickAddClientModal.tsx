import { useState, type FormEvent } from 'react'
import { useMutation } from '@tanstack/react-query'
import { Button, Field, SelectField } from '@/core/ui/primitives'
import { useToast } from '@/core/ui/useToast'
import { Modal } from '@/admin/ui/Modal'
import { ApiError } from '@/core/api/types'
import { CLIENT_TYPES, type ClientRef, clientTypeLabel, createClient } from '@/legal/api/clients'

/**
 * إضافة عميل سريعة (Phase 3 / PR-2) — تُستدعى فقط من داخل إنشاء القضية لإزالة
 * العائق حين لا يكون العميل موجوداً. ليست إدارة عملاء. تستخدم POST /clients فقط.
 */
export function QuickAddClientModal({ onCreated, onClose }: { onCreated: (c: ClientRef) => void; onClose: () => void }) {
  const { show } = useToast()
  const [name, setName] = useState('')
  const [type, setType] = useState('individual')

  const create = useMutation({
    mutationFn: () => createClient({ name: name.trim(), type }),
    onSuccess: (client) => {
      show('تم إنشاء العميل')
      onCreated(client)
      onClose()
    },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّر إنشاء العميل', 'error'),
  })

  function onSubmit(e: FormEvent) {
    e.preventDefault()
    create.mutate()
  }

  return (
    <Modal title="عميل جديد (سريع)" onClose={onClose}>
      <form onSubmit={onSubmit} className="space-y-3">
        <Field label="اسم العميل *" value={name} onChange={(e) => setName(e.target.value)} required />
        <SelectField label="النوع *" value={type} onChange={(e) => setType(e.target.value)}>
          {CLIENT_TYPES.map((t) => <option key={t} value={t}>{clientTypeLabel(t)}</option>)}
        </SelectField>
        <p className="text-xs text-slate-400">إضافة سريعة لإتمام إنشاء القضية. الإدارة الكاملة للعملاء من شاشتها المخصّصة لاحقاً.</p>
        <div className="flex justify-end gap-2 pt-1">
          <Button type="button" variant="ghost" onClick={onClose} disabled={create.isPending}>إلغاء</Button>
          <Button type="submit" disabled={create.isPending}>{create.isPending ? 'جارٍ…' : 'إضافة'}</Button>
        </div>
      </form>
    </Modal>
  )
}
