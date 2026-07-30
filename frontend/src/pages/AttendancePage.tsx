import { useState } from 'react'
import { useAttendance } from '../api/attendance'
import { Card, Field } from '../components/ui/primitives'
import { EmptyState, ErrorState, Skeleton } from '../components/ui/states'
import { attendanceStatusLabel, formatMinutes, formatTime } from '../lib/format'

export function AttendancePage() {
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const { data, isPending, isError, error } = useAttendance(from || undefined, to || undefined)

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold">حضوري</h1>

      <Card>
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <Field label="من تاريخ" type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
          <Field label="إلى تاريخ" type="date" value={to} onChange={(e) => setTo(e.target.value)} />
        </div>
      </Card>

      {isPending ? (
        <TableSkeleton />
      ) : isError ? (
        <ErrorState error={error} />
      ) : data.length === 0 ? (
        <EmptyState message="لا توجد سجلّات حضور ضمن النطاق المحدّد." />
      ) : (
        <Card className="overflow-x-auto p-0">
          <table className="w-full min-w-[640px] text-sm">
            <thead>
              <tr className="border-b border-slate-200 text-slate-500">
                {['التاريخ', 'الحالة', 'الدخول', 'الخروج', 'التأخير', 'الإضافي'].map((h) => (
                  <th key={h} className="p-3 text-right font-medium">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {data.map((r) => (
                <tr key={r.date} className="border-b border-slate-100 last:border-0">
                  <td className="p-3 font-medium text-slate-800">{r.date}</td>
                  <td className="p-3">{attendanceStatusLabel(r.status)}</td>
                  <td className="p-3">{formatTime(r.check_in)}</td>
                  <td className="p-3">{formatTime(r.check_out)}</td>
                  <td className="p-3">{formatMinutes(r.late_minutes)}</td>
                  <td className="p-3">{formatMinutes(r.overtime_minutes)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      )}
    </div>
  )
}

function TableSkeleton() {
  return (
    <div data-testid="attendance-skeleton">
      <Card>
        {Array.from({ length: 5 }).map((_, i) => (
          <Skeleton key={i} className="mb-2 h-6 w-full" />
        ))}
      </Card>
    </div>
  )
}
