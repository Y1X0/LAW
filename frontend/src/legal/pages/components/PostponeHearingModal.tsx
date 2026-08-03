import { useState, type FormEvent } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Button, Field, TextareaField } from '@/core/ui/primitives'
import { useToast } from '@/core/ui/useToast'
import { Modal } from '@/admin/ui/Modal'
import { ApiError } from '@/core/api/types'
import { postponeHearing } from '@/legal/api/hearings'

/**
 * تأجيل جلسة (Phase 3 / PR-4) — `POST /hearings/{id}/postpone`. سلوك الخادم:
 * تُؤرشَف الجلسة الحالية (postponed) وتُنشأ جلسة جديدة (scheduled) بالموعد الجديد.
 */
export function PostponeHearingModal({ caseId, hearingId, onClose }: { caseId: number; hearingId: number; onClose: () => void }) {
  const qc = useQueryClient()
  const { show } = useToast()
  const [scheduledAt, setScheduledAt] = useState('')
  const [reason, setReason] = useState('')

  const save = useMutation({
    mutationFn: () => postponeHearing(hearingId, scheduledAt, reason.trim()),
    onSuccess: () => {
      show('تم تأجيل الجلسة وإنشاء موعد جديد')
      void qc.invalidateQueries({ queryKey: ['legal', 'case-hearings', caseId] })
      onClose()
    },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّر التأجيل', 'error'),
  })

  function onSubmit(e: FormEvent) {
    e.preventDefault()
    save.mutate()
  }

  return (
    <Modal title="تأجيل الجلسة" onClose={onClose}>
      <form onSubmit={onSubmit} className="space-y-3">
        <Field label="الموعد الجديد *" type="datetime-local" value={scheduledAt} onChange={(e) => setScheduledAt(e.target.value)} required />
        <TextareaField label="سبب التأجيل *" value={reason} onChange={(e) => setReason(e.target.value)} rows={2} required />
        <p className="text-xs text-slate-400">ستبقى الجلسة الحالية في السجلّ كـ«مؤجّلة»، وتُنشأ جلسة جديدة بالموعد أعلاه.</p>
        <div className="flex justify-end gap-2 pt-1">
          <Button type="button" variant="ghost" onClick={onClose} disabled={save.isPending}>إلغاء</Button>
          <Button type="submit" disabled={save.isPending}>{save.isPending ? 'جارٍ…' : 'تأكيد التأجيل'}</Button>
        </div>
      </form>
    </Modal>
  )
}
