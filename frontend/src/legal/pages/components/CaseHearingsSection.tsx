import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Badge, Button, Card } from '@/core/ui/primitives'
import { SectionCard } from '@/core/ui/section'
import { EmptyState, ErrorState, Skeleton } from '@/core/ui/states'
import { useToast } from '@/core/ui/useToast'
import { ApiError } from '@/core/api/types'
import {
  type Hearing,
  cancelHearing,
  fetchCaseHearings,
  formatDateTime,
  hearingStatusLabel,
  hearingStatusTone,
  isHearingFrozen,
} from '@/legal/api/hearings'
import { HearingFormModal } from './HearingFormModal'
import { PostponeHearingModal } from './PostponeHearingModal'

/**
 * جلسات القضية (Phase 3 / PR-4) — قائمة + جدولة/تعديل/تأجيل/إلغاء، فوق العقود
 * الموجودة فقط. الجلسة المنعقدة (held) للقراءة فقط (تُعطَّل إجراءاتها) — تعكس
 * قاعدة الخادم guardNotHeld. تحديث العرض بعد كل عملية عبر invalidate.
 */
export function CaseHearingsSection({ caseId }: { caseId: number }) {
  const [scheduling, setScheduling] = useState(false)
  const [editing, setEditing] = useState<Hearing | null>(null)
  const [postponing, setPostponing] = useState<Hearing | null>(null)

  const query = useQuery({ queryKey: ['legal', 'case-hearings', caseId], queryFn: () => fetchCaseHearings(caseId) })

  return (
    <SectionCard title="الجلسات" action={<Button onClick={() => setScheduling(true)}>جدولة جلسة</Button>}>
      {query.isPending ? (
        <Skeleton className="h-16 w-full" />
      ) : query.isError ? (
        <ErrorState error={query.error}><div className="mt-3"><Button onClick={() => void query.refetch()}>إعادة المحاولة</Button></div></ErrorState>
      ) : query.data.length === 0 ? (
        <EmptyState message="لا توجد جلسات لهذه القضية." />
      ) : (
        <ul className="space-y-2.5">
          {query.data.map((h) => (
            <HearingRow key={h.id} caseId={caseId} hearing={h} onEdit={() => setEditing(h)} onPostpone={() => setPostponing(h)} />
          ))}
        </ul>
      )}

      {scheduling && <HearingFormModal caseId={caseId} onClose={() => setScheduling(false)} />}
      {editing && <HearingFormModal caseId={caseId} existing={editing} onClose={() => setEditing(null)} />}
      {postponing && <PostponeHearingModal caseId={caseId} hearingId={postponing.id} onClose={() => setPostponing(null)} />}
    </SectionCard>
  )
}

function HearingRow({ caseId, hearing, onEdit, onPostpone }: { caseId: number; hearing: Hearing; onEdit: () => void; onPostpone: () => void }) {
  const qc = useQueryClient()
  const { show } = useToast()
  const [confirming, setConfirming] = useState(false)
  const frozen = isHearingFrozen(hearing.status)

  const cancel = useMutation({
    mutationFn: () => cancelHearing(hearing.id),
    onSuccess: () => {
      show('تم إلغاء الجلسة')
      void qc.invalidateQueries({ queryKey: ['legal', 'case-hearings', caseId] })
    },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّر الإلغاء', 'error'),
  })

  return (
    <li>
      <Card className="flex flex-col gap-3 p-3.5 sm:flex-row sm:items-center sm:justify-between">
        <div className="min-w-0">
          <div className="font-medium tabular-nums text-slate-800">{formatDateTime(hearing.scheduled_at)}</div>
          <div className="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-slate-500">
            {hearing.type && <span>{hearing.type}</span>}
            {hearing.location && <span className="text-slate-400">{hearing.location}</span>}
          </div>
          {hearing.status === 'held' && hearing.outcome && <div className="mt-1 text-xs text-slate-600">النتيجة: {hearing.outcome}</div>}
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <Badge tone={hearingStatusTone(hearing.status)}>{hearingStatusLabel(hearing.status)}</Badge>
          {frozen ? (
            <span className="text-xs text-slate-400">للقراءة فقط (منعقدة)</span>
          ) : hearing.status === 'cancelled' || hearing.status === 'postponed' ? null : (
            <>
              <Button variant="ghost" onClick={onEdit}>تعديل</Button>
              <Button variant="ghost" onClick={onPostpone}>تأجيل</Button>
              {confirming ? (
                <span className="flex items-center gap-1.5">
                  <Button variant="ghost" onClick={() => cancel.mutate()} disabled={cancel.isPending}>تأكيد الإلغاء</Button>
                  <Button variant="ghost" onClick={() => setConfirming(false)} disabled={cancel.isPending}>تراجع</Button>
                </span>
              ) : (
                <Button variant="ghost" onClick={() => setConfirming(true)}>إلغاء الجلسة</Button>
              )}
            </>
          )}
        </div>
      </Card>
    </li>
  )
}
