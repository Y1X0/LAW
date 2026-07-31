import { useEffect, useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ApiError } from '@/core/api/types'
import { Button, Card, TextareaField } from '@/core/ui/primitives'
import { PageHeader, SectionCard } from '@/core/ui/section'
import { ErrorState, Skeleton } from '@/core/ui/states'
import { formatDate, formatDateTime } from '@/core/lib/format'
import { fetchMyWorklog, submitWorklog, todayISO } from '@/lawyer/api/worklog'

/**
 * الإنجاز اليومي (LP-5) — قراءة + كتابة عبر me/worklog فقط.
 * حالة اليوم واضحة (ابدأ/تحديث) + إشعار نجاح + تحديث مباشر (invalidate).
 */
export function WorklogPage() {
  const qc = useQueryClient()
  const query = useQuery({ queryKey: ['me', 'worklog'], queryFn: fetchMyWorklog })

  const today = todayISO()
  const logs = query.data ?? []
  const todayLog = logs.find((l) => l.work_date === today) ?? null
  const previous = logs.filter((l) => l.work_date !== today)

  const [doneToday, setDoneToday] = useState('')
  const [planTomorrow, setPlanTomorrow] = useState('')
  const [saved, setSaved] = useState(false)

  const hasToday = !!todayLog
  const todayDone = todayLog?.done_today ?? ''
  const todayPlan = todayLog?.plan_tomorrow ?? ''
  useEffect(() => {
    if (hasToday) {
      setDoneToday(todayDone)
      setPlanTomorrow(todayPlan)
    }
  }, [hasToday, todayDone, todayPlan])

  const mutation = useMutation({
    mutationFn: submitWorklog,
    onSuccess: () => {
      setSaved(true)
      void qc.invalidateQueries({ queryKey: ['me', 'worklog'] })
    },
  })

  const fieldError = mutation.error instanceof ApiError ? mutation.error.fields?.done_today?.[0] : undefined
  const genericError =
    mutation.isError && !(mutation.error instanceof ApiError && mutation.error.fields)
      ? mutation.error instanceof Error
        ? mutation.error.message
        : 'تعذّر الحفظ.'
      : undefined

  function onSubmit(e: FormEvent) {
    e.preventDefault()
    setSaved(false)
    mutation.mutate({ done_today: doneToday.trim(), plan_tomorrow: planTomorrow.trim() || null })
  }

  return (
    <div className="space-y-5">
      <PageHeader title="الإنجازات اليومية" subtitle="سجّل إنجاز يومك وخطة غدك" />

      {query.isPending ? (
        <Card>
          <Skeleton className="h-5 w-40" />
          <Skeleton className="mt-3 h-24 w-full" />
          <Skeleton className="mt-3 h-24 w-full" />
        </Card>
      ) : query.isError ? (
        <ErrorState error={query.error}>
          <div className="mt-3">
            <Button onClick={() => void query.refetch()}>إعادة المحاولة</Button>
          </div>
        </ErrorState>
      ) : (
        <>
          <Card>
            <div className="mb-3 flex items-center justify-between">
              <h2 className="text-sm font-semibold text-slate-700">
                {todayLog ? 'إنجاز اليوم' : 'ابدأ تسجيل إنجاز اليوم'}
              </h2>
              {todayLog?.updated_at ? (
                <span className="text-xs text-slate-400">آخر تحديث: {formatDateTime(todayLog.updated_at)}</span>
              ) : null}
            </div>

            {saved ? (
              <div className="mb-3 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700">
                تم حفظ إنجاز اليوم.
              </div>
            ) : null}

            <form onSubmit={onSubmit} className="space-y-3">
              <TextareaField
                label="ما أنجزته اليوم"
                rows={4}
                value={doneToday}
                onChange={(e) => {
                  setDoneToday(e.target.value)
                  setSaved(false)
                }}
                error={fieldError}
                required
              />
              <TextareaField
                label="خطة الغد (اختياري)"
                rows={3}
                value={planTomorrow}
                onChange={(e) => {
                  setPlanTomorrow(e.target.value)
                  setSaved(false)
                }}
              />
              {genericError ? <p className="text-sm text-red-600">{genericError}</p> : null}
              <Button type="submit" disabled={!doneToday.trim() || mutation.isPending}>
                {mutation.isPending ? 'جارٍ الحفظ…' : todayLog ? 'تحديث' : 'حفظ'}
              </Button>
            </form>
          </Card>

          {previous.length > 0 ? (
            <SectionCard title="سجلّاتي السابقة">
              <div className="space-y-3">
                {previous.slice(0, 7).map((l) => (
                  <div key={l.id} className="border-b border-slate-100 pb-2 last:border-0 last:pb-0">
                    <div className="text-xs font-medium tabular-nums text-brand-700">{formatDate(l.work_date)}</div>
                    <p className="mt-0.5 whitespace-pre-line text-sm text-slate-700">{l.done_today ?? '—'}</p>
                  </div>
                ))}
              </div>
            </SectionCard>
          ) : null}
        </>
      )}
    </div>
  )
}
