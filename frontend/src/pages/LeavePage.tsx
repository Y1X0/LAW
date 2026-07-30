import { useState, type FormEvent } from 'react'
import { useLeaveBalance, useLeaveRequests, useSubmitLeave, type LeaveBalance } from '../api/leave'
import { ApiError } from '../api/types'
import { Button, Field, SelectField, TextareaField } from '../components/ui/primitives'
import { InfoRow, SectionCard, Stat } from '../components/ui/section'
import { EmptyState, ErrorState, LoadingState } from '../components/ui/states'
import { leaveStatusLabel } from '../lib/format'

export function LeavePage() {
  const balance = useLeaveBalance()

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold">إجازاتي</h1>
      {balance.isPending ? (
        <LoadingState />
      ) : balance.isError ? (
        <ErrorState error={balance.error} />
      ) : (
        <>
          <BalanceCard balance={balance.data} />
          <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <RequestsCard />
            <SubmitCard balance={balance.data} />
          </div>
        </>
      )}
    </div>
  )
}

function BalanceCard({ balance }: { balance: LeaveBalance }) {
  return (
    <SectionCard title={`رصيد الإجازات (${balance.year})`}>
      <Stat label="أيام متبقّية" value={balance.total_remaining} hint="إجمالي كل الأنواع" />
      <div className="mt-3">
        {balance.by_type.map((t) => (
          <InfoRow key={t.leave_type_id} label={t.type ?? '—'} value={`${t.remaining} / ${t.entitled}`} />
        ))}
      </div>
    </SectionCard>
  )
}

function RequestsCard() {
  const { data, isPending, isError, error } = useLeaveRequests()

  return (
    <SectionCard title="طلباتي">
      {isPending ? (
        <LoadingState />
      ) : isError ? (
        <ErrorState error={error} />
      ) : data.length === 0 ? (
        <EmptyState message="لا توجد طلبات إجازة بعد." />
      ) : (
        <ul className="space-y-2">
          {data.map((r) => (
            <li key={r.id} className="rounded-lg border border-slate-100 p-2 text-sm">
              <div className="flex items-center justify-between">
                <span className="font-medium">{r.type ?? '—'}</span>
                <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">
                  {leaveStatusLabel(r.status)}
                </span>
              </div>
              <div className="mt-1 text-xs text-slate-500">
                {r.start_date} ← {r.end_date} · {r.days} يوم
              </div>
            </li>
          ))}
        </ul>
      )}
    </SectionCard>
  )
}

function SubmitCard({ balance }: { balance: LeaveBalance }) {
  const submit = useSubmitLeave()
  const [typeId, setTypeId] = useState('')
  const [start, setStart] = useState('')
  const [end, setEnd] = useState('')
  const [reason, setReason] = useState('')

  function onSubmit(e: FormEvent) {
    e.preventDefault()
    submit.mutate(
      { leave_type_id: Number(typeId), start_date: start, end_date: end, reason: reason || undefined },
      {
        onSuccess: () => {
          setStart('')
          setEnd('')
          setReason('')
        },
      },
    )
  }

  const fieldErrors = submit.error instanceof ApiError ? submit.error.fields : undefined

  return (
    <SectionCard title="تقديم طلب إجازة">
      {balance.by_type.length === 0 ? (
        <EmptyState message="لا أنواع إجازات متاحة للتقديم." />
      ) : (
        <form onSubmit={onSubmit} className="space-y-3">
          <SelectField
            label="نوع الإجازة"
            value={typeId}
            onChange={(e) => setTypeId(e.target.value)}
            required
            error={fieldErrors?.leave_type_id?.[0]}
          >
            <option value="" disabled>اختر النوع…</option>
            {balance.by_type.map((t) => (
              <option key={t.leave_type_id} value={t.leave_type_id}>{t.type ?? `#${t.leave_type_id}`}</option>
            ))}
          </SelectField>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <Field label="من" type="date" value={start} onChange={(e) => setStart(e.target.value)} required error={fieldErrors?.start_date?.[0]} />
            <Field label="إلى" type="date" value={end} onChange={(e) => setEnd(e.target.value)} required error={fieldErrors?.end_date?.[0]} />
          </div>
          <TextareaField label="السبب (اختياري)" rows={2} value={reason} onChange={(e) => setReason(e.target.value)} />

          {submit.isError && !fieldErrors ? (
            <p role="alert" className="text-sm text-red-600">
              {submit.error instanceof ApiError ? submit.error.message : 'تعذّر تقديم الطلب.'}
            </p>
          ) : null}
          {submit.isSuccess ? <p className="text-sm text-green-600">تم تقديم طلبك بنجاح (قيد المراجعة).</p> : null}

          <Button type="submit" className="w-full" disabled={submit.isPending}>
            {submit.isPending ? 'جارٍ التقديم…' : 'تقديم الطلب'}
          </Button>
        </form>
      )}
    </SectionCard>
  )
}
