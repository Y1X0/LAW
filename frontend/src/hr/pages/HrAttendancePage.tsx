import { useState } from 'react'
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Badge, Button, Card, Field, SelectField } from '@/core/ui/primitives'
import { PageHeader } from '@/core/ui/section'
import { EmptyState, ErrorState, Skeleton } from '@/core/ui/states'
import { Toast } from '@/core/ui/Toast'
import { useToast } from '@/core/ui/useToast'
import { formatDate, formatDateTime } from '@/core/lib/format'
import {
  ATTENDANCE_STATUSES,
  type AttendanceRecord,
  type ManualAttendanceInput,
  approveAttendance,
  attendanceStatusLabel,
  attendanceStatusTone,
  createManualAttendance,
  fetchAttendance,
} from '@/hr/api/attendance'
import { ManualAttendanceForm } from './ManualAttendanceForm'

const PER_PAGE = 15

/**
 * إدارة الحضور (HR-6) — عرض السجلات مع فلترة بالتاريخ/الحالة، قيد يدوي، واعتماد،
 * عبر APIs الموجودة فقط (بلا باك-إند). تغذية راجعة عبر توست.
 */
export function HrAttendancePage() {
  const qc = useQueryClient()
  const { toast, show } = useToast()
  const [date, setDate] = useState('')
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const [formOpen, setFormOpen] = useState(false)

  const { data, isPending, isError, error, refetch, isFetching } = useQuery({
    queryKey: ['hr', 'attendance', { date, status, page }],
    queryFn: () => fetchAttendance({ date, status, page, perPage: PER_PAGE }),
    placeholderData: keepPreviousData,
  })

  function invalidate() {
    void qc.invalidateQueries({ queryKey: ['hr', 'attendance'] })
  }

  const manual = useMutation({
    mutationFn: (input: ManualAttendanceInput) => createManualAttendance(input),
    onSuccess: () => {
      show('تم حفظ القيد اليدوي')
      setFormOpen(false)
      invalidate()
    },
    onError: () => show('تعذّر حفظ القيد', 'error'),
  })

  const approve = useMutation({
    mutationFn: (id: number) => approveAttendance(id),
    onSuccess: () => {
      show('تم اعتماد السجل')
      invalidate()
    },
    onError: () => show('تعذّر الاعتماد', 'error'),
  })

  function onDate(v: string) {
    setPage(1)
    setDate(v)
  }
  function onStatus(v: string) {
    setPage(1)
    setStatus(v)
  }

  const hasFilters = date !== '' || status !== ''

  return (
    <div className="space-y-5">
      <PageHeader
        title="الحضور"
        subtitle="سجلات حضور الموظفين"
        action={
          <Button variant={formOpen ? 'ghost' : 'primary'} onClick={() => setFormOpen((v) => !v)}>
            {formOpen ? 'إغلاق' : 'قيد يدوي'}
          </Button>
        }
      />

      {formOpen ? (
        <ManualAttendanceForm submitting={manual.isPending} onSubmit={(input) => manual.mutate(input)} onCancel={() => setFormOpen(false)} />
      ) : null}

      {/* فلترة بالتاريخ والحالة */}
      <Card>
        <div className="flex flex-col gap-3 md:flex-row md:items-end">
          <div className="md:w-52">
            <Field label="التاريخ" type="date" value={date} onChange={(e) => onDate(e.target.value)} />
          </div>
          <div className="md:w-52">
            <SelectField label="الحالة" value={status} onChange={(e) => onStatus(e.target.value)}>
              <option value="">كل الحالات</option>
              {ATTENDANCE_STATUSES.map((s) => (
                <option key={s} value={s}>
                  {attendanceStatusLabel(s)}
                </option>
              ))}
            </SelectField>
          </div>
        </div>
      </Card>

      {isPending ? (
        <AttendanceSkeleton />
      ) : isError ? (
        <ErrorState error={error}>
          <div className="mt-3">
            <Button onClick={() => void refetch()}>إعادة المحاولة</Button>
          </div>
        </ErrorState>
      ) : data.items.length === 0 ? (
        <Card>
          <EmptyState message={hasFilters ? 'لا توجد سجلات مطابقة.' : 'لا توجد سجلات حضور.'} />
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
                  <th className="px-4 py-3 text-right">التاريخ</th>
                  <th className="px-4 py-3 text-right">الحضور</th>
                  <th className="px-4 py-3 text-right">الانصراف</th>
                  <th className="px-4 py-3 text-right">تأخير (د)</th>
                  <th className="px-4 py-3 text-right">الحالة</th>
                  <th className="px-4 py-3 text-right">الاعتماد</th>
                </tr>
              </thead>
              <tbody>
                {data.items.map((rec) => (
                  <AttendanceRow
                    key={rec.id}
                    rec={rec}
                    approving={approve.isPending && approve.variables === rec.id}
                    onApprove={() => approve.mutate(rec.id)}
                  />
                ))}
              </tbody>
            </table>
          </Card>

          <div className="flex items-center justify-between gap-3 text-sm text-slate-600">
            <span className="tabular-nums">
              صفحة {data.meta.page} من {data.meta.total_pages} · {data.meta.total} سجل
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

      <Toast toast={toast} />
    </div>
  )
}

function AttendanceRow({ rec, approving, onApprove }: { rec: AttendanceRecord; approving: boolean; onApprove: () => void }) {
  return (
    <tr className="border-b border-slate-100 last:border-0">
      <td className="px-4 py-3">
        <div className="font-medium text-slate-800">{rec.employee?.full_name_ar ?? '—'}</div>
        <div className="tabular-nums text-xs text-slate-400">{rec.employee?.employee_no ?? ''}</div>
      </td>
      <td className="px-4 py-3 tabular-nums text-slate-700">{formatDate(rec.work_date)}</td>
      <td className="px-4 py-3 tabular-nums text-slate-600">{formatDateTime(rec.check_in ?? null)}</td>
      <td className="px-4 py-3 tabular-nums text-slate-600">{formatDateTime(rec.check_out ?? null)}</td>
      <td className="px-4 py-3 tabular-nums text-slate-600">{rec.late_minutes ?? 0}</td>
      <td className="px-4 py-3">
        <Badge tone={attendanceStatusTone(rec.status)}>{attendanceStatusLabel(rec.status)}</Badge>
      </td>
      <td className="px-4 py-3">
        {rec.approved_at ? (
          <Badge tone="green">معتمد</Badge>
        ) : (
          <Button variant="ghost" onClick={onApprove} disabled={approving}>
            {approving ? 'جارٍ…' : 'اعتماد'}
          </Button>
        )}
      </td>
    </tr>
  )
}

function AttendanceSkeleton() {
  return (
    <Card>
      <div data-testid="attendance-skeleton" className="space-y-3">
        {Array.from({ length: 6 }).map((_, i) => (
          <Skeleton key={i} className="h-9 w-full" />
        ))}
      </div>
    </Card>
  )
}
