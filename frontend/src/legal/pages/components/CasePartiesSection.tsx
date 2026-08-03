import { useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Badge, Button, Card, Field, SelectField, TextareaField } from '@/core/ui/primitives'
import { SectionCard } from '@/core/ui/section'
import { EmptyState, ErrorState, Skeleton } from '@/core/ui/states'
import { useToast } from '@/core/ui/useToast'
import { Modal } from '@/admin/ui/Modal'
import { ApiError } from '@/core/api/types'
import { PARTY_TYPES, createParty, fetchParties, partyTypeLabel } from '@/legal/api/content'

/**
 * أطراف القضية (Phase 3 / PR-5) — إضافة/عرض فقط. الخادم لا يدعم تعديل/حذف طرف،
 * فلا تُعرض تلك الإجراءات (الواجهة تعكس قيود الخادم).
 */
export function CasePartiesSection({ caseId }: { caseId: number }) {
  const [adding, setAdding] = useState(false)
  const query = useQuery({ queryKey: ['legal', 'case-parties', caseId], queryFn: () => fetchParties(caseId) })

  return (
    <SectionCard title="الأطراف" action={<Button onClick={() => setAdding(true)}>إضافة طرف</Button>}>
      {query.isPending ? <Skeleton className="h-14 w-full" /> :
       query.isError ? <ErrorState error={query.error} /> :
       query.data.length === 0 ? <EmptyState message="لا يوجد أطراف مسجّلون." /> : (
        <ul className="space-y-2.5">
          {query.data.map((p) => (
            <li key={p.id}>
              <Card className="flex flex-col gap-2 p-3.5 sm:flex-row sm:items-center sm:justify-between">
                <div className="min-w-0">
                  <div className="font-medium text-slate-800">{p.name}</div>
                  {p.phone && <div className="mt-0.5 tabular-nums text-xs text-slate-400">{p.phone}</div>}
                </div>
                <Badge tone="slate">{partyTypeLabel(p.type)}</Badge>
              </Card>
            </li>
          ))}
        </ul>
      )}
      {adding && <AddPartyModal caseId={caseId} onClose={() => setAdding(false)} />}
    </SectionCard>
  )
}

function AddPartyModal({ caseId, onClose }: { caseId: number; onClose: () => void }) {
  const qc = useQueryClient()
  const { show } = useToast()
  const [name, setName] = useState('')
  const [type, setType] = useState('plaintiff')
  const [phone, setPhone] = useState('')
  const [notes, setNotes] = useState('')
  const save = useMutation({
    mutationFn: () => createParty(caseId, { name: name.trim(), type, phone: phone.trim() || null, notes: notes.trim() || null }),
    onSuccess: () => { show('تمت إضافة الطرف'); void qc.invalidateQueries({ queryKey: ['legal', 'case-parties', caseId] }); onClose() },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّرت الإضافة', 'error'),
  })
  function onSubmit(e: FormEvent) { e.preventDefault(); save.mutate() }
  return (
    <Modal title="إضافة طرف" onClose={onClose}>
      <form onSubmit={onSubmit} className="space-y-3">
        <Field label="الاسم *" value={name} onChange={(e) => setName(e.target.value)} required />
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <SelectField label="الصفة *" value={type} onChange={(e) => setType(e.target.value)}>
            {PARTY_TYPES.map((t) => <option key={t} value={t}>{partyTypeLabel(t)}</option>)}
          </SelectField>
          <Field label="الهاتف" value={phone} onChange={(e) => setPhone(e.target.value)} />
        </div>
        <TextareaField label="ملاحظات" value={notes} onChange={(e) => setNotes(e.target.value)} rows={2} />
        <div className="flex justify-end gap-2 pt-1">
          <Button type="button" variant="ghost" onClick={onClose} disabled={save.isPending}>إلغاء</Button>
          <Button type="submit" disabled={save.isPending}>{save.isPending ? 'جارٍ…' : 'إضافة'}</Button>
        </div>
      </form>
    </Modal>
  )
}
