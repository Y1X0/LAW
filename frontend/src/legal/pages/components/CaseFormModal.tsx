import { useEffect, useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Button, Field, SelectField, TextareaField } from '@/core/ui/primitives'
import { useToast } from '@/core/ui/useToast'
import { Modal } from '@/admin/ui/Modal'
import { ApiError } from '@/core/api/types'
import { type ClientRef, fetchClients } from '@/legal/api/clients'
import { CASE_STATUSES, type CaseDetail, type CaseInput, caseStatusLabel, createCase, fetchCaseCustomFieldForm, updateCase } from '@/legal/api/cases'
import { QuickAddClientModal } from './QuickAddClientModal'
import { CaseCustomFieldsSection } from './CaseCustomFieldsSection'

/**
 * نموذج إنشاء/تعديل قضية (Phase 3 / PR-2) — POST/PUT /cases الموجودان.
 * العميل يُختار من الموجود، مع إضافة سريعة (Quick Add) عند غيابه فقط.
 */
export function CaseFormModal({ existing, onSaved, onClose }: { existing?: CaseDetail; onSaved: (c: CaseDetail) => void; onClose: () => void }) {
  const qc = useQueryClient()
  const { show } = useToast()
  const editing = !!existing
  const [f, setF] = useState({
    internal_number: existing?.internal_number ?? '',
    title: existing?.title ?? '',
    court_case_number: existing?.court_case_number ?? '',
    court_name: existing?.court_name ?? '',
    case_type: existing?.case_type ?? '',
    value: existing?.value != null ? String(existing.value) : '',
    status: existing?.status ?? 'open',
    progress: existing?.progress != null ? String(existing.progress) : '0',
    opened_date: existing?.opened_date ?? '',
    description: existing?.description ?? '',
  })
  const [clientId, setClientId] = useState<number | ''>(existing?.client?.id ?? '')
  const [addingClient, setAddingClient] = useState(false)
  const [extraClients, setExtraClients] = useState<ClientRef[]>([])

  // مخطّط الحقول المخصّصة من الخادم (Phase 12) — editable يقرّر مدخل أم للعرض فقط.
  const cfForm = useQuery({
    queryKey: ['legal', 'case-custom-fields', editing ? existing!.id : 'new'],
    queryFn: () => fetchCaseCustomFieldForm(editing ? 'edit' : 'create', existing?.id),
  })
  const [cf, setCf] = useState<Record<string, string | boolean>>({})
  useEffect(() => {
    if (cfForm.data && Object.keys(cf).length === 0) {
      const init: Record<string, string | boolean> = {}
      for (const item of cfForm.data) init[item.key] = item.type === 'boolean' ? Boolean(item.value) : (item.value ?? '') as string
      setCf(init)
    }
  }, [cfForm.data, cf])

  const clients = useQuery({ queryKey: ['legal', 'clients-picker'], queryFn: () => fetchClients() })
  const options = [...(clients.data ?? []), ...extraClients]
  // العميل الحالي للقضية (عند التعديل) قد لا يكون ضمن أول 100 نشِط — أضِفه للخيارات.
  if (existing?.client && !options.some((c) => c.id === existing.client!.id)) {
    options.unshift({ id: existing.client.id, name: existing.client.name })
  }

  const set = (k: keyof typeof f) => (e: { target: { value: string } }) => setF((s) => ({ ...s, [k]: e.target.value }))

  const save = useMutation({
    mutationFn: () => {
      const payload: CaseInput = {
        internal_number: f.internal_number.trim(),
        title: f.title.trim(),
        client_id: Number(clientId),
        court_case_number: f.court_case_number.trim() || null,
        court_name: f.court_name.trim() || null,
        case_type: f.case_type.trim() || null,
        value: f.value.trim() ? Number(f.value) : null,
        status: f.status,
        progress: Number(f.progress) || 0,
        opened_date: f.opened_date || null,
        description: f.description.trim() || null,
      }
      // قيم الحقول القابلة للتعديل فقط (الخادم يرفض غيرها على أي حال).
      const editable = (cfForm.data ?? []).filter((i) => i.editable)
      if (editable.length > 0) {
        payload.custom_fields = Object.fromEntries(editable.map((i) => [i.key, cf[i.key] ?? '']))
      }
      return editing ? updateCase(existing!.id, payload) : createCase(payload)
    },
    onSuccess: (c) => {
      show(editing ? 'تم تحديث القضية' : 'تم إنشاء القضية')
      void qc.invalidateQueries({ queryKey: ['legal', 'cases'] })
      if (editing) void qc.invalidateQueries({ queryKey: ['legal', 'case', existing!.id] })
      onSaved(c)
      onClose()
    },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّر حفظ القضية', 'error'),
  })

  function onSubmit(e: FormEvent) {
    e.preventDefault()
    if (clientId === '') { show('اختر عميلاً', 'error'); return }
    save.mutate()
  }

  return (
    <Modal title={editing ? 'تعديل القضية' : 'إنشاء قضية'} onClose={onClose}>
      <form onSubmit={onSubmit} className="space-y-3">
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <Field label="الرقم الداخلي *" value={f.internal_number} onChange={set('internal_number')} required />
          <Field label="رقم القضية بالمحكمة" value={f.court_case_number} onChange={set('court_case_number')} />
        </div>
        <Field label="العنوان *" value={f.title} onChange={set('title')} required />

        <div>
          <div className="flex items-end gap-2">
            <div className="flex-1">
              <SelectField label="العميل *" value={clientId === '' ? '' : String(clientId)} onChange={(e) => setClientId(e.target.value ? Number(e.target.value) : '')}>
                <option value="">— اختر عميلاً —</option>
                {options.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
              </SelectField>
            </div>
            <Button type="button" variant="ghost" onClick={() => setAddingClient(true)}>عميل جديد</Button>
          </div>
        </div>

        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <Field label="المحكمة" value={f.court_name} onChange={set('court_name')} />
          <Field label="نوع القضية" value={f.case_type} onChange={set('case_type')} />
          <Field label="القيمة" type="number" value={f.value} onChange={set('value')} min={0} step="0.01" />
          <Field label="تاريخ الفتح" type="date" value={f.opened_date} onChange={set('opened_date')} />
          <SelectField label="الحالة" value={f.status} onChange={set('status')}>
            {CASE_STATUSES.map((s) => <option key={s} value={s}>{caseStatusLabel(s)}</option>)}
          </SelectField>
          <Field label="التقدّم %" type="number" value={f.progress} onChange={set('progress')} min={0} max={100} />
        </div>
        <TextareaField label="الوصف" value={f.description} onChange={set('description')} rows={3} />

        {cfForm.data && (
          <CaseCustomFieldsSection
            items={cfForm.data}
            values={cf}
            onChange={(key, value) => setCf((s) => ({ ...s, [key]: value }))}
          />
        )}

        <div className="flex justify-end gap-2 pt-1">
          <Button type="button" variant="ghost" onClick={onClose} disabled={save.isPending}>إلغاء</Button>
          <Button type="submit" disabled={save.isPending}>{save.isPending ? 'جارٍ…' : editing ? 'حفظ' : 'إنشاء'}</Button>
        </div>
      </form>

      {addingClient && (
        <QuickAddClientModal
          onCreated={(c) => { setExtraClients((x) => [c, ...x]); setClientId(c.id) }}
          onClose={() => setAddingClient(false)}
        />
      )}
    </Modal>
  )
}
