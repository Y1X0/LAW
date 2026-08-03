import { useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Button, Card, Field, TextareaField } from '@/core/ui/primitives'
import { SectionCard } from '@/core/ui/section'
import { EmptyState, ErrorState, Skeleton } from '@/core/ui/states'
import { useToast } from '@/core/ui/useToast'
import { Modal } from '@/admin/ui/Modal'
import { ApiError } from '@/core/api/types'
import {
  DOCUMENT_ACCEPT,
  DOCUMENT_MAX_MB,
  type CaseDocument,
  createDocument,
  deleteDocument,
  downloadDocument,
  fetchDocuments,
  formatFileSize,
} from '@/legal/api/content'

/**
 * وثائق القضية (Phase 5 / PR-2) — رفع فعلي إلى R2 وتنزيل محروس عبر الخادم.
 * الخادم يقرّر المسار/القرص ويفحص النوع (بالمحتوى) والحجم؛ لا روابط عامة.
 */
export function CaseDocumentsSection({ caseId }: { caseId: number }) {
  const [adding, setAdding] = useState(false)
  const query = useQuery({ queryKey: ['legal', 'case-docs', caseId], queryFn: () => fetchDocuments(caseId) })

  return (
    <SectionCard title="الوثائق" action={<Button onClick={() => setAdding(true)}>إضافة وثيقة</Button>}>
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
  const download = useMutation({
    mutationFn: () => downloadDocument(doc),
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّر التنزيل', 'error'),
  })
  const meta = [doc.document_type, formatFileSize(doc.size_bytes)].filter(Boolean).join(' · ')
  return (
    <li>
      <Card className="flex flex-col gap-2 p-3.5 sm:flex-row sm:items-center sm:justify-between">
        <div className="min-w-0">
          <div className="font-medium text-slate-800">{doc.title}</div>
          <div className="mt-0.5 text-xs text-slate-500">{doc.original_name ?? (meta || '—')}{doc.original_name && meta ? ` · ${meta}` : ''}</div>
        </div>
        <span className="flex flex-shrink-0 items-center gap-1.5">
          <Button variant="ghost" onClick={() => download.mutate()} disabled={download.isPending}>{download.isPending ? 'جارٍ…' : 'تنزيل'}</Button>
          {confirming ? (
            <>
              <Button variant="ghost" onClick={() => del.mutate()} disabled={del.isPending}>تأكيد الحذف</Button>
              <Button variant="ghost" onClick={() => setConfirming(false)} disabled={del.isPending}>تراجع</Button>
            </>
          ) : (
            <Button variant="ghost" onClick={() => setConfirming(true)}>حذف</Button>
          )}
        </span>
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
  const [file, setFile] = useState<File | null>(null)
  const save = useMutation({
    mutationFn: () => createDocument(caseId, { title: title.trim(), document_type: type.trim() || null, description: description.trim() || null, file: file! }),
    onSuccess: () => { show('تمت إضافة الوثيقة'); void qc.invalidateQueries({ queryKey: ['legal', 'case-docs', caseId] }); onClose() },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّرت الإضافة', 'error'),
  })
  const canSubmit = title.trim().length > 0 && file != null && !save.isPending
  function onSubmit(e: FormEvent) { e.preventDefault(); if (canSubmit) save.mutate() }
  return (
    <Modal title="إضافة وثيقة" onClose={onClose}>
      <form onSubmit={onSubmit} className="space-y-3">
        <Field label="العنوان *" value={title} onChange={(e) => setTitle(e.target.value)} required />
        <Field label="النوع" value={type} onChange={(e) => setType(e.target.value)} placeholder="مذكرة · لائحة · عقد · حكم · وكالة" />
        <label className="block text-sm font-medium text-slate-700">
          الملف *
          <input
            type="file"
            accept={DOCUMENT_ACCEPT}
            onChange={(e) => setFile(e.target.files?.[0] ?? null)}
            className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-1 file:text-brand-700 focus:border-brand-500 focus:outline-none"
            aria-required="true"
          />
        </label>
        <p className="text-xs text-slate-400">المسموح: PDF · صورة (JPG/PNG) · Word (DOC/DOCX). الحد الأقصى {DOCUMENT_MAX_MB} ميغابايت.</p>
        <TextareaField label="الوصف" value={description} onChange={(e) => setDescription(e.target.value)} rows={2} />
        <div className="flex justify-end gap-2 pt-1">
          <Button type="button" variant="ghost" onClick={onClose} disabled={save.isPending}>إلغاء</Button>
          <Button type="submit" disabled={!canSubmit}>{save.isPending ? 'جارٍ الرفع…' : 'رفع'}</Button>
        </div>
      </form>
    </Modal>
  )
}
