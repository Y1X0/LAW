import { useQuery } from '@tanstack/react-query'
import { Badge } from '@/core/ui/primitives'
import { formatDate, formatDateTime } from '@/core/lib/format'
import { AsyncPanel } from '@/hr/components/AsyncPanel'
import { HrDataTable } from '@/hr/components/HrDataTable'
import { attendanceStatusLabel, attendanceStatusTone, fetchEmployeeAttendance } from '@/hr/api/employeeProfile'

/** تبويب الحضور — GET /attendance?employee_id={id} (آخر 15 سجلاً، قراءة). */
export function AttendanceTab({ employeeId }: { employeeId: string }) {
  const q = useQuery({
    queryKey: ['hr', 'employee', employeeId, 'attendance'],
    queryFn: () => fetchEmployeeAttendance(employeeId),
  })

  return (
    <AsyncPanel q={q} isEmpty={(d) => d.length === 0} emptyMessage="لا توجد سجلات حضور لهذا الموظف.">
      {(records) => (
        <HrDataTable head={['التاريخ', 'الحضور', 'الانصراف', 'تأخير (د)', 'الحالة']}>
          {records.map((r) => (
            <tr key={r.id} className="border-b border-slate-100 last:border-0">
              <td className="px-4 py-3 tabular-nums text-slate-700">{formatDate(r.work_date)}</td>
              <td className="px-4 py-3 tabular-nums text-slate-600">{formatDateTime(r.check_in ?? null)}</td>
              <td className="px-4 py-3 tabular-nums text-slate-600">{formatDateTime(r.check_out ?? null)}</td>
              <td className="px-4 py-3 tabular-nums text-slate-600">{r.late_minutes ?? 0}</td>
              <td className="px-4 py-3">
                <Badge tone={attendanceStatusTone(r.status)}>{attendanceStatusLabel(r.status)}</Badge>
              </td>
            </tr>
          ))}
        </HrDataTable>
      )}
    </AsyncPanel>
  )
}
