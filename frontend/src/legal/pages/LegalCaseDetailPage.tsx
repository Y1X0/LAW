import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link, useParams } from 'react-router-dom'
import { Badge, Button, Card } from '@/core/ui/primitives'
import { PageHeader, SectionCard } from '@/core/ui/section'
import { EmptyState, ErrorState, Skeleton } from '@/core/ui/states'
import { useToast } from '@/core/ui/useToast'
import { ApiError } from '@/core/api/types'
import { formatCurrency } from '@/core/lib/format'
import { assignmentRoleLabel, caseStatusLabel, caseStatusTone, closeCase, fetchCase, unassignLawyer } from '@/legal/api/cases'
import { CaseFormModal } from './components/CaseFormModal'
import { AssignLawyerModal } from './components/AssignLawyerModal'
import { CaseHearingsSection } from './components/CaseHearingsSection'
import { CaseDocumentsSection } from './components/CaseDocumentsSection'
import { CasePartiesSection } from './components/CasePartiesSection'
import { CaseTimelineSection } from './components/CaseTimelineSection'
import { CaseArchiveSection } from './components/CaseArchiveSection'

/**
 * تفاصيل القضية للمدير (Phase 3 / PR-2) — من `GET /cases/{id}` الموجود، مع تعديل
 * وإغلاق. المحامون المسندون للعرض فقط هنا؛ إدارة الإسناد في PR-3.
 */
export function LegalCaseDetailPage() {
  const { id: idParam } = useParams()
  const id = Number(idParam)
  const qc = useQueryClient()
  const { show } = useToast()
  const [editing, setEditing] = useState(false)
  const [confirmClose, setConfirmClose] = useState(false)
  const [assigning, setAssigning] = useState(false)

  const query = useQuery({ queryKey: ['legal', 'case', id], queryFn: () => fetchCase(id), enabled: Number.isFinite(id) })

  const close = useMutation({
    mutationFn: () => closeCase(id),
    onSuccess: () => {
      show('تم إغلاق القضية')
      void qc.invalidateQueries({ queryKey: ['legal', 'case', id] })
      void qc.invalidateQueries({ queryKey: ['legal', 'cases'] })
      setConfirmClose(false)
    },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّر الإغلاق', 'error'),
  })

  if (query.isPending) return <Card><Skeleton className="h-40 w-full" /></Card>
  if (query.isError) {
    return <ErrorState error={query.error}><div className="mt-3"><Button onClick={() => void query.refetch()}>إعادة المحاولة</Button></div></ErrorState>
  }

  const c = query.data
  const closed = c.status === 'closed'

  return (
    <div className="space-y-5">
      <PageHeader
        title={c.title}
        subtitle={c.internal_number}
        action={
          <div className="flex flex-wrap items-center gap-2">
            <Link to="/legal" className="lp-press rounded-lg px-3 py-2 text-sm font-medium text-brand-700 hover:bg-brand-50">القضايا</Link>
            <Button variant="ghost" onClick={() => setEditing(true)}>تعديل</Button>
            {!closed && (confirmClose ? (
              <span className="flex items-center gap-1.5">
                <Button variant="ghost" onClick={() => close.mutate()} disabled={close.isPending}>تأكيد الإغلاق</Button>
                <Button variant="ghost" onClick={() => setConfirmClose(false)} disabled={close.isPending}>إلغاء</Button>
              </span>
            ) : (
              <Button variant="ghost" onClick={() => setConfirmClose(true)}>إغلاق القضية</Button>
            ))}
          </div>
        }
      />

      <SectionCard title="بيانات القضية">
        <div className="grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
          <Info label="الحالة" value={<Badge tone={caseStatusTone(c.status)}>{caseStatusLabel(c.status)}</Badge>} />
          <Info label="التقدّم" value={`${c.progress}%`} />
          <Info label="العميل" value={c.client ? <Link to={`/legal/clients/${c.client.id}`} className="text-brand-700 hover:underline">{c.client.name}</Link> : '—'} />
          <Info label="المحامي المسؤول" value={c.responsibleLawyer?.full_name_ar ?? '—'} />
          <Info label="رقم المحكمة" value={c.court_case_number ?? '—'} />
          <Info label="المحكمة" value={c.court_name ?? '—'} />
          <Info label="نوع القضية" value={c.case_type ?? '—'} />
          <Info label="القيمة" value={c.value != null ? formatCurrency(c.value, 'SAR') : '—'} />
          <Info label="تاريخ الفتح" value={c.opened_date ?? '—'} />
        </div>
        {c.description && <p className="mt-3 border-t border-slate-100 pt-3 text-sm text-slate-600">{c.description}</p>}
      </SectionCard>

      {c.custom_fields.length > 0 && (
        <SectionCard title="الحقول المخصّصة">
          <div className="grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
            {c.custom_fields.map((f) => (
              <Info
                key={f.key}
                label={f.label}
                value={f.type === 'boolean' ? (f.value ? 'نعم' : 'لا') : (f.value === null || f.value === '' ? '—' : String(f.value))}
              />
            ))}
          </div>
        </SectionCard>
      )}

      <SectionCard title="المحامون المسندون" action={<Button onClick={() => setAssigning(true)}>إسناد محامٍ</Button>}>
        {c.assignments.length === 0 ? (
          <EmptyState message="لا يوجد محامون مسندون لهذه القضية." />
        ) : (
          <ul className="space-y-2">
            {c.assignments.map((a) => (
              <AssignmentRow key={a.id} caseId={id} employeeId={a.employee?.id ?? null} name={a.employee?.full_name_ar ?? '—'} role={a.role} />
            ))}
          </ul>
        )}
      </SectionCard>

      <CaseHearingsSection caseId={id} />
      <CaseDocumentsSection caseId={id} />
      <CasePartiesSection caseId={id} />
      <CaseTimelineSection caseId={id} />
      <CaseArchiveSection caseId={id} />

      {editing && <CaseFormModal existing={c} onSaved={() => {}} onClose={() => setEditing(false)} />}
      {assigning && <AssignLawyerModal caseId={id} onClose={() => setAssigning(false)} />}
    </div>
  )
}

function AssignmentRow({ caseId, employeeId, name, role }: { caseId: number; employeeId: number | null; name: string; role: string }) {
  const qc = useQueryClient()
  const { show } = useToast()
  const [confirming, setConfirming] = useState(false)

  const remove = useMutation({
    mutationFn: () => unassignLawyer(caseId, employeeId!),
    onSuccess: () => {
      show('تم إلغاء الإسناد')
      void qc.invalidateQueries({ queryKey: ['legal', 'case', caseId] })
    },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّر إلغاء الإسناد', 'error'),
  })

  return (
    <li className="flex items-center justify-between gap-3 text-sm">
      <span className="font-medium text-slate-800">{name}</span>
      <div className="flex items-center gap-2">
        <Badge tone={role === 'lead' ? 'green' : 'slate'}>{assignmentRoleLabel(role)}</Badge>
        {employeeId != null && (confirming ? (
          <span className="flex items-center gap-1.5">
            <Button variant="ghost" onClick={() => remove.mutate()} disabled={remove.isPending}>تأكيد</Button>
            <Button variant="ghost" onClick={() => setConfirming(false)} disabled={remove.isPending}>إلغاء</Button>
          </span>
        ) : (
          <Button variant="ghost" onClick={() => setConfirming(true)}>إزالة</Button>
        ))}
      </div>
    </li>
  )
}

function Info({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div>
      <div className="text-xs text-slate-400">{label}</div>
      <div className="font-medium text-slate-800">{value}</div>
    </div>
  )
}
