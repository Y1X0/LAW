import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link, useParams } from 'react-router-dom'
import { Badge, Button, Card } from '@/core/ui/primitives'
import { PageHeader, SectionCard } from '@/core/ui/section'
import { EmptyState, ErrorState, Skeleton } from '@/core/ui/states'
import { useToast } from '@/core/ui/useToast'
import { ApiError } from '@/core/api/types'
import { caseStatusLabel, caseStatusTone, fetchCases } from '@/legal/api/cases'
import { clientStatusLabel, clientStatusTone, clientTypeLabel, fetchClient, setClientStatus } from '@/legal/api/clients'
import { ClientFormModal } from './components/ClientFormModal'

/**
 * تفاصيل العميل (Phase 4 / PR-2) — من `GET /clients/{id}` الموجود، مع تعديل
 * (إعادة استخدام ClientFormModal) وتبديل الحالة (تفعيل/تعطيل — بديل الحذف، لا حذف
 * فعلي) وقضايا العميل من `GET /cases?client_id=`. صفر تغيير خلفي.
 */
export function LegalClientDetailPage() {
  const { id: idParam } = useParams()
  const id = Number(idParam)
  const qc = useQueryClient()
  const { show } = useToast()
  const [editing, setEditing] = useState(false)
  const [confirmToggle, setConfirmToggle] = useState(false)

  const query = useQuery({ queryKey: ['legal', 'client', id], queryFn: () => fetchClient(id), enabled: Number.isFinite(id) })

  const toggle = useMutation({
    mutationFn: (next: string) => setClientStatus(id, next),
    onSuccess: (c) => {
      show(c.status === 'active' ? 'تم تفعيل العميل' : 'تم تعطيل العميل')
      void qc.invalidateQueries({ queryKey: ['legal', 'client', id] })
      void qc.invalidateQueries({ queryKey: ['legal', 'clients'] })
      setConfirmToggle(false)
    },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّر تغيير الحالة', 'error'),
  })

  if (query.isPending) return <Card><Skeleton className="h-40 w-full" /></Card>
  if (query.isError) {
    return <ErrorState error={query.error}><div className="mt-3"><Button onClick={() => void query.refetch()}>إعادة المحاولة</Button></div></ErrorState>
  }

  const c = query.data
  const active = c.status === 'active'

  return (
    <div className="space-y-5">
      <PageHeader
        title={c.name}
        subtitle={clientTypeLabel(c.type)}
        action={
          <div className="flex flex-wrap items-center gap-2">
            <Link to="/legal/clients" className="lp-press rounded-lg px-3 py-2 text-sm font-medium text-brand-700 hover:bg-brand-50">العملاء</Link>
            <Button variant="ghost" onClick={() => setEditing(true)}>تعديل</Button>
            {active ? (
              confirmToggle ? (
                <span className="flex items-center gap-1.5">
                  <Button variant="ghost" onClick={() => toggle.mutate('inactive')} disabled={toggle.isPending}>تأكيد التعطيل</Button>
                  <Button variant="ghost" onClick={() => setConfirmToggle(false)} disabled={toggle.isPending}>تراجع</Button>
                </span>
              ) : (
                <Button variant="ghost" onClick={() => setConfirmToggle(true)}>تعطيل</Button>
              )
            ) : (
              <Button variant="ghost" onClick={() => toggle.mutate('active')} disabled={toggle.isPending}>تفعيل</Button>
            )}
          </div>
        }
      />

      {active && confirmToggle && (
        <Card className="border-amber-200 bg-amber-50 text-sm text-amber-700">
          التعطيل يوقف ظهور العميل في القوائم والاختيار الافتراضي — وهو ليس حذفًا؛ تبقى بياناته وقضاياه، ويمكن تفعيله مجددًا.
        </Card>
      )}

      <SectionCard title="بيانات العميل">
        <div className="grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
          <Info label="الحالة" value={<Badge tone={clientStatusTone(c.status)}>{clientStatusLabel(c.status)}</Badge>} />
          <Info label="النوع" value={clientTypeLabel(c.type)} />
          <Info label="الهاتف" value={c.phone ?? '—'} />
          <Info label="البريد" value={c.email ?? '—'} />
          <Info label="الهوية / السجل" value={c.national_id ?? '—'} />
          <Info label="العنوان" value={c.address ?? '—'} />
        </div>
        {c.notes && <p className="mt-3 border-t border-slate-100 pt-3 text-sm text-slate-600">{c.notes}</p>}
      </SectionCard>

      <ClientCasesSection clientId={id} />

      {editing && <ClientFormModal existing={c} onClose={() => setEditing(false)} />}
    </div>
  )
}

function ClientCasesSection({ clientId }: { clientId: number }) {
  const query = useQuery({
    queryKey: ['legal', 'client-cases', clientId],
    queryFn: () => fetchCases({ clientId, perPage: 100 }),
  })

  const count = query.data?.meta.total
  return (
    <SectionCard title={`قضايا العميل${count != null ? ` (${count})` : ''}`}>
      {query.isPending ? <Skeleton className="h-14 w-full" /> :
       query.isError ? <ErrorState error={query.error}><div className="mt-3"><Button onClick={() => void query.refetch()}>إعادة المحاولة</Button></div></ErrorState> :
       query.data.items.length === 0 ? <EmptyState message="لا توجد قضايا مرتبطة بهذا العميل." /> : (
        <ul className="space-y-2.5">
          {query.data.items.map((c) => (
            <li key={c.id}>
              <Card className="flex flex-col gap-2 p-3.5 sm:flex-row sm:items-center sm:justify-between">
                <div className="min-w-0">
                  <Link to={`/legal/cases/${c.id}`} className="font-medium text-brand-700 hover:underline">{c.title}</Link>
                  <div className="mt-0.5 tabular-nums text-xs text-slate-400">{c.internal_number}</div>
                </div>
                <Badge tone={caseStatusTone(c.status)}>{caseStatusLabel(c.status)}</Badge>
              </Card>
            </li>
          ))}
        </ul>
      )}
    </SectionCard>
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
