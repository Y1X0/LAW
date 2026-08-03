import { useState, type FormEvent } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Button, Field, SelectField, TextareaField } from '@/core/ui/primitives'
import { useToast } from '@/core/ui/useToast'
import { Modal } from '@/admin/ui/Modal'
import { ApiError } from '@/core/api/types'
import { type Hearing, createHearing, hearingStatusLabel, updateHearing } from '@/legal/api/hearings'

/**
 * جدولة/تعديل جلسة (Phase 3 / PR-4) — POST/PUT الموجودان. عند التعديل يمكن تعليم
 * الجلسة «منعقدة» (held) مع تسجيل النتيجة؛ الإلغاء/التأجيل عمليتان منفصلتان.
 * لا يُفتح هذا النموذج لجلسة منعقدة (مجمّدة على الخادم).
 */
export function HearingFormModal({ caseId, existing, onClose }: { caseId: number; existing?: Hearing; onClose: () => void }) {
  const qc = useQueryClient()
  const { show } = useToast()
  const editing = !!existing
  const [scheduledAt, setScheduledAt] = useState((existing?.scheduled_at ?? '').replace(' ', 'T').slice(0, 16))
  const [type, setType] = useState(existing?.type ?? '')
  const [location, setLocation] = useState(existing?.location ?? '')
  const [notes, setNotes] = useState(existing?.notes ?? '')
  const [status, setStatus] = useState(existing?.status ?? 'scheduled')
  const [outcome, setOutcome] = useState(existing?.outcome ?? '')

  const save = useMutation({
    mutationFn: () =>
      editing
        ? updateHearing(existing!.id, {
            scheduled_at: scheduledAt || undefined,
            type: type.trim() || null,
            location: location.trim() || null,
            notes: notes.trim() || null,
            status,
            outcome: outcome.trim() || null,
          })
        : createHearing(caseId, {
            scheduled_at: scheduledAt,
            type: type.trim() || null,
            location: location.trim() || null,
            notes: notes.trim() || null,
          }),
    onSuccess: () => {
      show(editing ? 'تم تحديث الجلسة' : 'تمت جدولة الجلسة')
      void qc.invalidateQueries({ queryKey: ['legal', 'case-hearings', caseId] })
      onClose()
    },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّر حفظ الجلسة', 'error'),
  })

  function onSubmit(e: FormEvent) {
    e.preventDefault()
    save.mutate()
  }

  return (
    <Modal title={editing ? 'تعديل الجلسة' : 'جدولة جلسة'} onClose={onClose}>
      <form onSubmit={onSubmit} className="space-y-3">
        <Field label="التاريخ والوقت *" type="datetime-local" value={scheduledAt} onChange={(e) => setScheduledAt(e.target.value)} required />
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <Field label="النوع" value={type} onChange={(e) => setType(e.target.value)} placeholder="مرافعة · تمهيدية · نطق بالحكم" />
          <Field label="المكان" value={location} onChange={(e) => setLocation(e.target.value)} />
        </div>
        {editing && (
          <>
            <SelectField label="الحالة" value={status} onChange={(e) => setStatus(e.target.value)}>
              <option value="scheduled">{hearingStatusLabel('scheduled')}</option>
              <option value="held">{hearingStatusLabel('held')}</option>
            </SelectField>
            {status === 'held' && (
              <TextareaField label="النتيجة" value={outcome} onChange={(e) => setOutcome(e.target.value)} rows={2} placeholder="نتيجة الجلسة عند انعقادها" />
            )}
            <p className="text-xs text-slate-400">تعليمها «منعقدة» يجمّدها (لا تُعدَّل بعدها). الإلغاء والتأجيل من أزرار الجلسة.</p>
          </>
        )}
        <TextareaField label="ملاحظات" value={notes} onChange={(e) => setNotes(e.target.value)} rows={2} />
        <div className="flex justify-end gap-2 pt-1">
          <Button type="button" variant="ghost" onClick={onClose} disabled={save.isPending}>إلغاء</Button>
          <Button type="submit" disabled={save.isPending}>{save.isPending ? 'جارٍ…' : editing ? 'حفظ' : 'جدولة'}</Button>
        </div>
      </form>
    </Modal>
  )
}
