import { useState } from 'react'
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Badge, Button, Card, Field, TextareaField } from '@/core/ui/primitives'
import { PageHeader } from '@/core/ui/section'
import { EmptyState, ErrorState, Skeleton } from '@/core/ui/states'
import { Tabs } from '@/core/ui/Tabs'
import { useToast } from '@/core/ui/useToast'
import { formatDate } from '@/core/lib/format'
import {
  LEAVE_TABS,
  type LeaveRequest,
  type LeaveTab,
  approveLeave,
  fetchLeaveRequests,
  leaveStatusLabel,
  leaveStatusTone,
  rejectLeave,
} from '@/hr/api/leave'

const PER_PAGE = 15
const TAB_LABEL: Record<LeaveTab, string> = { pending: 'المعلّقة', approved: 'المقبولة', rejected: 'المرفوضة' }
const TABS = LEAVE_TABS.map((key) => ({ key, label: TAB_LABEL[key] }))

/**
 * إدارة الإجازات (HR-5) — تبويبات حالة (معلّقة/مقبولة/مرفوضة) عبر فلتر status الخادمي،
 * وموافقة/رفض (مع سبب) عبر APIs الموجودة، وتغذية راجعة عبر توست. لا باك-إند جديد.
 */
export function HrLeavePage() {
  const qc = useQueryClient()
  const { show } = useToast()
  const [tab, setTab] = useState<LeaveTab>('pending')
  const [page, setPage] = useState(1)
  const [nameFilter, setNameFilter] = useState('')
  const [rejectingId, setRejectingId] = useState<number | null>(null)
  const [reason, setReason] = useState('')

  const { data, isPending, isError, error, refetch, isFetching } = useQuery({
    queryKey: ['hr', 'leave', { tab, page }],
    queryFn: () => fetchLeaveRequests(tab, page, PER_PAGE),
    placeholderData: keepPreviousData,
  })

  function invalidate() {
    void qc.invalidateQueries({ queryKey: ['hr', 'leave'] })
  }

  const approve = useMutation({
    mutationFn: (id: number) => approveLeave(id),
    onSuccess: () => {
      show('تمت الموافقة على الطلب')
      invalidate()
    },
    onError: () => show('تعذّر تنفيذ العملية', 'error'),
  })

  const reject = useMutation({
    mutationFn: (vars: { id: number; reason: string }) => rejectLeave(vars.id, vars.reason),
    onSuccess: () => {
      show('تم رفض الطلب')
      setRejectingId(null)
      setReason('')
      invalidate()
    },
    onError: () => show('تعذّر تنفيذ العملية', 'error'),
  })

  function switchTab(next: LeaveTab) {
    setPage(1)
    setRejectingId(null)
    setTab(next)
  }

  const rows = (data?.items ?? []).filter((r) =>
    nameFilter.trim() ? (r.employee?.full_name_ar ?? '').includes(nameFilter.trim()) : true,
  )

  return (
    <div className="space-y-5">
      <PageHeader title="الإجازات" subtitle="إدارة طلبات إجازات الموظفين" />

      <Tabs tabs={TABS} active={tab} onChange={(k) => switchTab(k as LeaveTab)} />

      {/* تصفية بالاسم (على الصفحة المحمّلة) */}
      <Card>
        <div className="md:w-72">
          <Field
            label="تصفية بالاسم"
            value={nameFilter}
            onChange={(e) => setNameFilter(e.target.value)}
            placeholder="اسم الموظف…"
          />
        </div>
      </Card>

      {isPending ? (
        <LeaveSkeleton />
      ) : isError ? (
        <ErrorState error={error}>
          <div className="mt-3">
            <Button onClick={() => void refetch()}>إعادة المحاولة</Button>
          </div>
        </ErrorState>
      ) : rows.length === 0 ? (
        <Card>
          <EmptyState message={nameFilter ? 'لا توجد نتائج مطابقة.' : 'لا توجد طلبات في هذه الحالة.'} />
        </Card>
      ) : (
        <>
          <Card className="lp-table-wrap p-0">
            <table
              className={`lp-table min-w-[820px] text-right text-sm transition-opacity ${isFetching ? 'opacity-60' : ''}`}
              aria-busy={isFetching}
            >
              <thead>
                <tr className="text-xs">
                  <th className="px-4 py-3 text-right">الموظف</th>
                  <th className="px-4 py-3 text-right">النوع</th>
                  <th className="px-4 py-3 text-right">من</th>
                  <th className="px-4 py-3 text-right">إلى</th>
                  <th className="px-4 py-3 text-right">الأيام</th>
                  <th className="px-4 py-3 text-right">السبب</th>
                  <th className="px-4 py-3 text-right">{tab === 'pending' ? 'إجراء' : 'الحالة'}</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((r) => (
                  <LeaveRow
                    key={r.id}
                    r={r}
                    tab={tab}
                    rejecting={rejectingId === r.id}
                    reason={reason}
                    busy={approve.isPending || reject.isPending}
                    onApprove={() => approve.mutate(r.id)}
                    onAskReject={() => {
                      setRejectingId(r.id)
                      setReason('')
                    }}
                    onReasonChange={setReason}
                    onCancelReject={() => setRejectingId(null)}
                    onConfirmReject={() => reject.mutate({ id: r.id, reason: reason.trim() })}
                  />
                ))}
              </tbody>
            </table>
          </Card>

          <div className="flex items-center justify-between gap-3 text-sm text-slate-600">
            <span className="tabular-nums">
              صفحة {data.meta.page} من {data.meta.total_pages} · {data.meta.total} طلب
            </span>
            <div className="flex gap-2">
              <Button variant="ghost" onClick={() => setPage((p) => Math.max(1, p - 1))} disabled={page <= 1 || isFetching}>
                السابق
              </Button>
              <Button
                variant="ghost"
                onClick={() => setPage((p) => Math.min(data.meta.total_pages, p + 1))}
                disabled={page >= data.meta.total_pages || isFetching}
              >
                التالي
              </Button>
            </div>
          </div>
        </>
      )}
    </div>
  )
}

function LeaveRow({
  r,
  tab,
  rejecting,
  reason,
  busy,
  onApprove,
  onAskReject,
  onReasonChange,
  onCancelReject,
  onConfirmReject,
}: {
  r: LeaveRequest
  tab: LeaveTab
  rejecting: boolean
  reason: string
  busy: boolean
  onApprove: () => void
  onAskReject: () => void
  onReasonChange: (v: string) => void
  onCancelReject: () => void
  onConfirmReject: () => void
}) {
  return (
    <>
      <tr className="border-b border-slate-100 last:border-0">
        <td className="px-4 py-3">
          <div className="font-medium text-slate-800">{r.employee?.full_name_ar ?? '—'}</div>
          <div className="tabular-nums text-xs text-slate-400">{r.employee?.employee_no ?? ''}</div>
        </td>
        <td className="px-4 py-3 text-slate-700">{r.leaveType?.name ?? '—'}</td>
        <td className="px-4 py-3 tabular-nums text-slate-600">{formatDate(r.start_date ?? null)}</td>
        <td className="px-4 py-3 tabular-nums text-slate-600">{formatDate(r.end_date ?? null)}</td>
        <td className="px-4 py-3 tabular-nums text-slate-600">{r.days ?? '—'}</td>
        <td className="px-4 py-3 text-slate-600">{r.reason ?? '—'}</td>
        <td className="px-4 py-3">
          {tab === 'pending' ? (
            <div className="flex items-center gap-2">
              <Button onClick={onApprove} disabled={busy}>
                موافقة
              </Button>
              <Button variant="ghost" onClick={onAskReject} disabled={busy}>
                رفض
              </Button>
            </div>
          ) : (
            <div>
              <Badge tone={leaveStatusTone(r.status)}>{leaveStatusLabel(r.status)}</Badge>
              {r.status === 'rejected' && r.rejection_reason ? (
                <div className="mt-1 max-w-xs text-xs text-slate-500">السبب: {r.rejection_reason}</div>
              ) : null}
            </div>
          )}
        </td>
      </tr>
      {rejecting ? (
        <tr className="border-b border-slate-100 bg-slate-50/60">
          <td colSpan={7} className="px-4 py-3">
            <div className="flex flex-col gap-2 sm:flex-row sm:items-end">
              <div className="flex-1">
                <TextareaField
                  label="سبب الرفض"
                  rows={2}
                  value={reason}
                  onChange={(e) => onReasonChange(e.target.value)}
                  placeholder="اكتب سبب الرفض…"
                />
              </div>
              <div className="flex gap-2">
                <Button onClick={onConfirmReject} disabled={busy || reason.trim() === ''}>
                  {busy ? 'جارٍ…' : 'تأكيد الرفض'}
                </Button>
                <Button variant="ghost" onClick={onCancelReject} disabled={busy}>
                  إلغاء
                </Button>
              </div>
            </div>
          </td>
        </tr>
      ) : null}
    </>
  )
}

function LeaveSkeleton() {
  return (
    <Card>
      <div data-testid="leave-skeleton" className="space-y-3">
        {Array.from({ length: 5 }).map((_, i) => (
          <Skeleton key={i} className="h-9 w-full" />
        ))}
      </div>
    </Card>
  )
}
