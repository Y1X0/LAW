import { Link } from 'react-router-dom'
import type { AttendanceToday, Dashboard, LastPayslip } from '@/employee/api/dashboard'
import { useDashboard } from '@/employee/api/dashboard'
import { ErrorState } from '@/core/ui/states'
import { InfoRow, SectionCard, Stat } from '@/core/ui/section'
import { DashboardSkeleton } from '@/employee/components/DashboardSkeleton'
import { attendanceStatusLabel, formatCurrency, formatMinutes, formatPeriod, formatTime } from '@/core/lib/format'

export function DashboardPage() {
  const { data, isPending, isError, error } = useDashboard()

  if (isPending) return <DashboardSkeleton />
  if (isError) return <ErrorState error={error} />

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold">لوحتي</h1>
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
        <ProfileCard profile={data.profile} />
        <LeaveBalanceCard balance={data.leave_balance} />
        <AttendanceTodayCard attendance={data.attendance_today} />
        <LastPayslipCard payslip={data.last_payslip} />
      </div>
    </div>
  )
}

function ProfileCard({ profile }: { profile: Dashboard['profile'] }) {
  return (
    <SectionCard title="بياناتي">
      <InfoRow label="الاسم" value={profile.name} />
      <InfoRow label="الرقم الوظيفي" value={profile.employee_number} />
      <InfoRow label="المسمّى الوظيفي" value={profile.job_title} />
      <InfoRow label="الفرع" value={profile.branch} />
      <InfoRow label="القسم" value={profile.department} />
      <InfoRow label="المدير" value={profile.manager} />
    </SectionCard>
  )
}

function LeaveBalanceCard({ balance }: { balance: Dashboard['leave_balance'] }) {
  return (
    <SectionCard title={`رصيد الإجازات (${balance.year})`} action={<Link className="text-xs text-brand-600 hover:underline" to="/leave">التفاصيل</Link>}>
      <Stat label="أيام متبقّية" value={balance.total_remaining} hint="إجمالي كل الأنواع" />
      {balance.by_type.length > 0 ? (
        <div className="mt-3">
          {balance.by_type.map((t, i) => (
            <InfoRow key={i} label={t.type ?? '—'} value={`${t.remaining} يوم`} />
          ))}
        </div>
      ) : null}
    </SectionCard>
  )
}

function AttendanceTodayCard({ attendance }: { attendance: AttendanceToday }) {
  return (
    <SectionCard title="حضور اليوم" action={<Link className="text-xs text-brand-600 hover:underline" to="/attendance">السجلّ</Link>}>
      {attendance === null ? (
        <p className="py-6 text-center text-sm text-slate-500">لم يُسجَّل حضور اليوم بعد.</p>
      ) : (
        <>
          <InfoRow label="الحالة" value={attendanceStatusLabel(attendance.status)} />
          <InfoRow label="الدخول" value={formatTime(attendance.check_in)} />
          <InfoRow label="الخروج" value={formatTime(attendance.check_out)} />
          <InfoRow label="التأخير" value={formatMinutes(attendance.late_minutes)} />
          <InfoRow label="الإضافي" value={formatMinutes(attendance.overtime_minutes)} />
        </>
      )}
    </SectionCard>
  )
}

function LastPayslipCard({ payslip }: { payslip: LastPayslip }) {
  return (
    <SectionCard title="آخر راتب" action={<Link className="text-xs text-brand-600 hover:underline" to="/payslips">كشوفي</Link>}>
      {payslip === null ? (
        <p className="py-6 text-center text-sm text-slate-500">لا يوجد راتب معتمد بعد.</p>
      ) : (
        <>
          <Stat label="صافي الراتب" value={formatCurrency(payslip.net, payslip.currency)} />
          <div className="mt-3">
            <InfoRow label="الفترة" value={formatPeriod(payslip.year, payslip.month)} />
          </div>
        </>
      )}
    </SectionCard>
  )
}
