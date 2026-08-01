import { useState, type ReactNode } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link, useParams } from 'react-router-dom'
import { Badge, Card } from '@/core/ui/primitives'
import { PageHeader } from '@/core/ui/section'
import { Tabs } from '@/core/ui/Tabs'
import { ErrorState, LoadingState } from '@/core/ui/states'
import { formatDate } from '@/core/lib/format'
import { employeeStatusLabel, employeeStatusTone } from '@/hr/api/employees'
import { fetchEmployeeDetail } from '@/hr/api/employeeProfile'
import { InfoTab } from './employeeProfile/InfoTab'
import { ContractsTab } from './employeeProfile/ContractsTab'
import { DocumentsTab } from './employeeProfile/DocumentsTab'
import { AttendanceTab } from './employeeProfile/AttendanceTab'
import { LeavesTab } from './employeeProfile/LeavesTab'
import { SalaryTab } from './employeeProfile/SalaryTab'

type TabKey = 'info' | 'contracts' | 'documents' | 'attendance' | 'leaves' | 'salary'

const TABS: { key: TabKey; label: string }[] = [
  { key: 'info', label: 'المعلومات' },
  { key: 'contracts', label: 'العقود' },
  { key: 'documents', label: 'الوثائق' },
  { key: 'attendance', label: 'الحضور' },
  { key: 'leaves', label: 'الإجازات' },
  { key: 'salary', label: 'الرواتب' },
]

/** ملف الموظف (HR-4) — رأس هوية + تبويبات قراءة على APIs موجودة (بلا باك-إند). */
export function HrEmployeeProfilePage() {
  const { id = '' } = useParams()
  const [tab, setTab] = useState<TabKey>('info')

  const q = useQuery({ queryKey: ['hr', 'employee', id, 'detail'], queryFn: () => fetchEmployeeDetail(id) })

  const backLink = (
    <Link to="/hr/employees" className="text-sm font-medium text-brand-700 hover:underline">
      ← كل الموظفين
    </Link>
  )

  if (q.isPending) {
    return (
      <div className="space-y-5">
        <PageHeader title="ملف الموظف" action={backLink} />
        <LoadingState />
      </div>
    )
  }
  if (q.isError) {
    return (
      <div className="space-y-5">
        <PageHeader title="ملف الموظف" action={backLink} />
        <ErrorState error={q.error} />
      </div>
    )
  }

  const emp = q.data

  return (
    <div className="space-y-5">
      {/* رأس هوية الموظف */}
      <div className="lp-reveal">
        <Card className="relative overflow-hidden">
          <span className="absolute inset-x-0 top-0 h-[3px] bg-gradient-to-l from-transparent via-gold-400 to-transparent" />
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div className="space-y-1">
              <div className="flex flex-wrap items-center gap-2.5">
                <h1 className="text-[22px] font-extrabold tracking-tight text-brand-700">{emp.full_name_ar}</h1>
                <Badge tone={employeeStatusTone(emp.status)}>{employeeStatusLabel(emp.status)}</Badge>
              </div>
              <p className="text-sm text-slate-500">{`الرقم الوظيفي: ${emp.employee_no}`}</p>
            </div>
            {backLink}
          </div>
          <div className="mt-4 flex flex-wrap gap-x-8 gap-y-2 text-sm">
            <Meta label="الوظيفة" value={emp.job_title ?? '—'} />
            <Meta label="القسم" value={emp.department?.name ?? '—'} />
            <Meta label="تاريخ التعيين" value={<span className="tabular-nums">{formatDate(emp.hire_date ?? null)}</span>} />
          </div>
        </Card>
      </div>

      <Tabs tabs={TABS} active={tab} onChange={setTab} />

      <div key={tab} role="tabpanel" className="lp-tab-panel">
        {tab === 'info' && <InfoTab emp={emp} />}
        {tab === 'contracts' && <ContractsTab employeeId={id} />}
        {tab === 'documents' && <DocumentsTab employeeId={id} />}
        {tab === 'attendance' && <AttendanceTab employeeId={id} />}
        {tab === 'leaves' && <LeavesTab employeeId={id} />}
        {tab === 'salary' && <SalaryTab employeeId={id} />}
      </div>
    </div>
  )
}

function Meta({ label, value }: { label: string; value: ReactNode }) {
  return (
    <div>
      <span className="text-slate-500">{label}: </span>
      <span className="font-medium text-slate-800">{value}</span>
    </div>
  )
}
