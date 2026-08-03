import { useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Button, Card, Field, TextareaField } from '@/core/ui/primitives'
import { SectionCard } from '@/core/ui/section'
import { EmptyState, ErrorState, Skeleton } from '@/core/ui/states'
import { useToast } from '@/core/ui/useToast'
import { Modal } from '@/admin/ui/Modal'
import { ApiError } from '@/core/api/types'
import {
  type ArchiveInput,
  type ArchiveLocation,
  createArchive,
  deleteArchive,
  fetchArchive,
  updateArchive,
} from '@/legal/api/content'

/**
 * الأرشيف الورقي للقضية (Phase 3 / PR-5) — CRUD كامل لمواقع الملف الورقي
 * (غرفة/خزانة/رف/درج/رقم الملف). فوق عقود archive-locations الموجودة فقط.
 */
export function CaseArchiveSection({ caseId }: { caseId: number }) {
  const [adding, setAdding] = useState(false)
  const query = useQuery({ queryKey: ['legal', 'case-archive', caseId], queryFn: () => fetchArchive(caseId) })

  return (
    <SectionCard title="الأرشيف الورقي" action={<Button onClick={() => setAdding(true)}>إضافة موقع</Button>}>
      {query.isPending ? <Skeleton className="h-14 w-full" /> :
       query.isError ? <ErrorState error={query.error}><div className="mt-3"><Button onClick={() => void query.refetch()}>إعادة المحاولة</Button></div></ErrorState> :
       query.data.length === 0 ? <EmptyState message="لا توجد مواقع أرشيف مسجّلة." /> : (
        <ul className="space-y-2.5">
          {query.data.map((loc) => <ArchiveRow key={loc.id} caseId={caseId} loc={loc} />)}
        </ul>
      )}
      {adding && <ArchiveFormModal caseId={caseId} onClose={() => setAdding(false)} />}
    </SectionCard>
  )
}

function ArchiveRow({ caseId, loc }: { caseId: number; loc: ArchiveLocation }) {
  const qc = useQueryClient()
  const { show } = useToast()
  const [editing, setEditing] = useState(false)
  const [confirming, setConfirming] = useState(false)
  const del = useMutation({
    mutationFn: () => deleteArchive(loc.id),
    onSuccess: () => { show('تم حذف الموقع'); void qc.invalidateQueries({ queryKey: ['legal', 'case-archive', caseId] }) },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّر الحذف', 'error'),
  })
  const parts = [loc.archive_room, loc.cabinet, loc.shelf, loc.drawer].filter(Boolean).join(' · ')
  return (
    <li>
      <Card className="flex flex-col gap-2 p-3.5 sm:flex-row sm:items-center sm:justify-between">
        <div className="min-w-0">
          <div className="font-medium text-slate-800">{loc.file_title}{loc.file_number ? <span className="mr-2 tabular-nums text-xs text-slate-400">#{loc.file_number}</span> : null}</div>
          {(parts || loc.notes) && <div className="mt-0.5 text-xs text-slate-500">{parts}{parts && loc.notes ? ' · ' : ''}{loc.notes ?? ''}</div>}
        </div>
        {confirming ? (
          <span className="flex items-center gap-1.5">
            <Button variant="ghost" onClick={() => del.mutate()} disabled={del.isPending}>تأكيد الحذف</Button>
            <Button variant="ghost" onClick={() => setConfirming(false)} disabled={del.isPending}>تراجع</Button>
          </span>
        ) : (
          <span className="flex items-center gap-1.5">
            <Button variant="ghost" onClick={() => setEditing(true)}>تعديل</Button>
            <Button variant="ghost" onClick={() => setConfirming(true)}>حذف</Button>
          </span>
        )}
      </Card>
      {editing && <ArchiveFormModal caseId={caseId} existing={loc} onClose={() => setEditing(false)} />}
    </li>
  )
}

function ArchiveFormModal({ caseId, existing, onClose }: { caseId: number; existing?: ArchiveLocation; onClose: () => void }) {
  const qc = useQueryClient()
  const { show } = useToast()
  const [fileTitle, setFileTitle] = useState(existing?.file_title ?? '')
  const [room, setRoom] = useState(existing?.archive_room ?? '')
  const [cabinet, setCabinet] = useState(existing?.cabinet ?? '')
  const [shelf, setShelf] = useState(existing?.shelf ?? '')
  const [drawer, setDrawer] = useState(existing?.drawer ?? '')
  const [fileNumber, setFileNumber] = useState(existing?.file_number ?? '')
  const [notes, setNotes] = useState(existing?.notes ?? '')

  const save = useMutation({
    mutationFn: () => {
      const input: ArchiveInput = {
        file_title: fileTitle.trim(),
        archive_room: room.trim() || null,
        cabinet: cabinet.trim() || null,
        shelf: shelf.trim() || null,
        drawer: drawer.trim() || null,
        file_number: fileNumber.trim() || null,
        notes: notes.trim() || null,
      }
      return existing ? updateArchive(existing.id, input) : createArchive(caseId, input)
    },
    onSuccess: () => {
      show(existing ? 'تم تحديث الموقع' : 'تمت إضافة الموقع')
      void qc.invalidateQueries({ queryKey: ['legal', 'case-archive', caseId] })
      onClose()
    },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّر الحفظ', 'error'),
  })
  function onSubmit(e: FormEvent) { e.preventDefault(); save.mutate() }
  return (
    <Modal title={existing ? 'تعديل موقع الأرشيف' : 'إضافة موقع أرشيف'} onClose={onClose}>
      <form onSubmit={onSubmit} className="space-y-3">
        <Field label="عنوان الملف *" value={fileTitle} onChange={(e) => setFileTitle(e.target.value)} required />
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <Field label="الغرفة" value={room} onChange={(e) => setRoom(e.target.value)} />
          <Field label="الخزانة" value={cabinet} onChange={(e) => setCabinet(e.target.value)} />
          <Field label="الرف" value={shelf} onChange={(e) => setShelf(e.target.value)} />
          <Field label="الدرج" value={drawer} onChange={(e) => setDrawer(e.target.value)} />
          <Field label="رقم الملف" value={fileNumber} onChange={(e) => setFileNumber(e.target.value)} />
        </div>
        <TextareaField label="ملاحظات" value={notes} onChange={(e) => setNotes(e.target.value)} rows={2} />
        <div className="flex justify-end gap-2 pt-1">
          <Button type="button" variant="ghost" onClick={onClose} disabled={save.isPending}>إلغاء</Button>
          <Button type="submit" disabled={save.isPending}>{save.isPending ? 'جارٍ…' : existing ? 'حفظ' : 'إضافة'}</Button>
        </div>
      </form>
    </Modal>
  )
}
