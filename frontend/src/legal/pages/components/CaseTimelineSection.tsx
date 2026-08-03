import { useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Button, Card, Field, TextareaField } from '@/core/ui/primitives'
import { SectionCard } from '@/core/ui/section'
import { EmptyState, ErrorState, Skeleton } from '@/core/ui/states'
import { useToast } from '@/core/ui/useToast'
import { Modal } from '@/admin/ui/Modal'
import { ApiError } from '@/core/api/types'
import { createTimelineEvent, fetchTimeline } from '@/legal/api/content'

/**
 * الخط الزمني للقضية (Phase 3 / PR-5) — عرض + إضافة حدث. Append-only على الخادم:
 * لا تعديل ولا حذف لأي حدث تاريخي (الواجهة لا تعرض تلك الإجراءات).
 */
export function CaseTimelineSection({ caseId }: { caseId: number }) {
  const [adding, setAdding] = useState(false)
  const query = useQuery({ queryKey: ['legal', 'case-timeline', caseId], queryFn: () => fetchTimeline(caseId) })

  return (
    <SectionCard title="الخط الزمني" action={<Button onClick={() => setAdding(true)}>إضافة حدث</Button>}>
      {query.isPending ? <Skeleton className="h-14 w-full" /> :
       query.isError ? <ErrorState error={query.error}><div className="mt-3"><Button onClick={() => void query.refetch()}>إعادة المحاولة</Button></div></ErrorState> :
       query.data.length === 0 ? <EmptyState message="لا توجد أحداث مسجّلة." /> : (
        <ul className="space-y-2.5">
          {query.data.map((ev) => (
            <li key={ev.id}>
              <Card className="p-3.5">
                <div className="flex items-center justify-between gap-2">
                  <span className="font-medium text-slate-800">{ev.title}</span>
                  <span className="tabular-nums text-xs text-slate-400">{ev.event_date ?? ''}</span>
                </div>
                {(ev.event_type || ev.description) && (
                  <div className="mt-1 text-xs text-slate-500">{ev.event_type ?? ''}{ev.description ? ` · ${ev.description}` : ''}</div>
                )}
              </Card>
            </li>
          ))}
        </ul>
      )}
      {adding && <AddEventModal caseId={caseId} onClose={() => setAdding(false)} />}
    </SectionCard>
  )
}

function AddEventModal({ caseId, onClose }: { caseId: number; onClose: () => void }) {
  const qc = useQueryClient()
  const { show } = useToast()
  const [title, setTitle] = useState('')
  const [type, setType] = useState('')
  const [date, setDate] = useState('')
  const [description, setDescription] = useState('')
  const save = useMutation({
    mutationFn: () => createTimelineEvent(caseId, { title: title.trim(), event_type: type.trim() || null, event_date: date, description: description.trim() || null }),
    onSuccess: () => { show('تمت إضافة الحدث'); void qc.invalidateQueries({ queryKey: ['legal', 'case-timeline', caseId] }); onClose() },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّرت الإضافة', 'error'),
  })
  function onSubmit(e: FormEvent) { e.preventDefault(); save.mutate() }
  return (
    <Modal title="إضافة حدث" onClose={onClose}>
      <form onSubmit={onSubmit} className="space-y-3">
        <Field label="العنوان *" value={title} onChange={(e) => setTitle(e.target.value)} required />
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <Field label="النوع" value={type} onChange={(e) => setType(e.target.value)} placeholder="إيداع · جلسة · مذكرة · حكم" />
          <Field label="التاريخ *" type="date" value={date} onChange={(e) => setDate(e.target.value)} required />
        </div>
        <TextareaField label="الوصف" value={description} onChange={(e) => setDescription(e.target.value)} rows={2} />
        <p className="text-xs text-slate-400">الخط الزمني سجلّ دائم — لا يُعدَّل ولا يُحذف بعد الإضافة.</p>
        <div className="flex justify-end gap-2 pt-1">
          <Button type="button" variant="ghost" onClick={onClose} disabled={save.isPending}>إلغاء</Button>
          <Button type="submit" disabled={save.isPending}>{save.isPending ? 'جارٍ…' : 'إضافة'}</Button>
        </div>
      </form>
    </Modal>
  )
}
