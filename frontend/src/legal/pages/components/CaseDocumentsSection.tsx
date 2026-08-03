import { useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Button, Card, Field, TextareaField } from '@/core/ui/primitives'
import { SectionCard } from '@/core/ui/section'
import { EmptyState, ErrorState, Skeleton } from '@/core/ui/states'
import { useToast } from '@/core/ui/useToast'
import { Modal } from '@/admin/ui/Modal'
import { ApiError } from '@/core/api/types'
import { type CaseDocument, createDocument, deleteDocument, fetchDocuments } from '@/legal/api/content'

/**
 * وثائق القضية (Phase 3 / PR-5) — بيانات وصفية فقط. الخادم لا يدعم رفع الملف
 * الفعلي بعد، فالواجهة تسجّل العنوان/النوع/الوصف وتوضّح ذلك صراحةً.
 */
export function CaseDocumentsSection({ caseId }: { caseId: number }) {
  const [adding, setAdding] = useState(false)
  const query = useQuery({ queryKey: ['legal', 'case-docs', caseId], queryFn: () => fetchDocuments(caseId) })

  return (
    <SectionCard title="الوثائق" action={<Button onClick={() => setAdding(true)}>إضافة وثيقة</Button>}>
      <p className="mb-3 text-xs text-amber-600">تُسجَّل بيانات الوثيقة الوصفية فقط — رفع الملف الفعلي غير مدعوم في الخادم حالياً.</p>
      {query.isPending ? <Skeleton className="h-14 w-full" /> :
       query.isError ? <ErrorState error={query.error}><div className="mt-3"><Button onClick={() => void query.refetch()}>إعادة المحاولة</Button></div></ErrorState> :
       query.data.length === 0 ? <EmptyState message="لا توجد وثائق مسجّلة." /> : (
        <ul className="space-y-2.5">
          {query.data.map((d) => <DocRow key={d.id} caseId={caseId} doc={d} />)}
        </ul>
      )}
      {adding && <AddDocModal caseId={caseId} onClose={() => setAdding(false)} />}
    </SectionCard>
  )
}

function DocRow({ caseId, doc }: { caseId: number; doc: CaseDocument }) {
  const qc = useQueryClient()
  const { show } = useToast()
  const [confirming, setConfirming] = useState(false)
  const del = useMutation({
    mutationFn: () => deleteDocument(doc.id),
    onSuccess: () => { show('تم حذف الوثيقة'); void qc.invalidateQueries({ queryKey: ['legal', 'case-docs', caseId] }) },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّر الحذف', 'error'),
  })
  return (
    <li>
      <Card className="flex flex-col gap-2 p-3.5 sm:flex-row sm:items-center sm:justify-between">
        <div className="min-w-0">
          <div className="font-medium text-slate-800">{doc.title}</div>
          <div className="mt-0.5 text-xs text-slate-500">{doc.document_type ?? '—'}{doc.description ? ` · ${doc.description}` : ''}</div>
        </div>
        {confirming ? (
          <span className="flex items-center gap-1.5">
            <Button variant="ghost" onClick={() => del.mutate()} disabled={del.isPending}>تأكيد الحذف</Button>
            <Button variant="ghost" onClick={() => setConfirming(false)} disabled={del.isPending}>تراجع</Button>
          </span>
        ) : (
          <Button variant="ghost" onClick={() => setConfirming(true)}>حذف</Button>
        )}
      </Card>
    </li>
  )
}

function AddDocModal({ caseId, onClose }: { caseId: number; onClose: () => void }) {
  const qc = useQueryClient()
  const { show } = useToast()
  const [title, setTitle] = useState('')
  const [type, setType] = useState('')
  const [description, setDescription] = useState('')
  const save = useMutation({
    mutationFn: () => createDocument(caseId, { title: title.trim(), document_type: type.trim() || null, description: description.trim() || null }),
    onSuccess: () => { show('تمت إضافة الوثيقة'); void qc.invalidateQueries({ queryKey: ['legal', 'case-docs', caseId] }); onClose() },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّرت الإضافة', 'error'),
  })
  function onSubmit(e: FormEvent) { e.preventDefault(); save.mutate() }
  return (
    <Modal title="إضافة وثيقة" onClose={onClose}>
      <form onSubmit={onSubmit} className="space-y-3">
        <Field label="العنوان *" value={title} onChange={(e) => setTitle(e.target.value)} required />
        <Field label="النوع" value={type} onChange={(e) => setType(e.target.value)} placeholder="مذكرة · لائحة · عقد · حكم · وكالة" />
        <TextareaField label="الوصف" value={description} onChange={(e) => setDescription(e.target.value)} rows={2} />
        <div className="flex justify-end gap-2 pt-1">
          <Button type="button" variant="ghost" onClick={onClose} disabled={save.isPending}>إلغاء</Button>
          <Button type="submit" disabled={save.isPending}>{save.isPending ? 'جارٍ…' : 'إضافة'}</Button>
        </div>
      </form>
    </Modal>
  )
}
