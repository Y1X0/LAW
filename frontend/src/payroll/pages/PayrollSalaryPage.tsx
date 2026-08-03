import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Badge, Button, Card } from '@/core/ui/primitives'
import { PageHeader, SectionCard } from '@/core/ui/section'
import { EmptyState, ErrorState, Skeleton } from '@/core/ui/states'
import { useToast } from '@/core/ui/useToast'
import { ApiError } from '@/core/api/types'
import { formatCurrency } from '@/core/lib/format'
import { fetchEmployees, type EmployeeListItem } from '@/hr/api/employees'
import { componentTypeLabel, componentTypeTone } from '@/payroll/api/salaryComponents'
import {
  type EmployeeComponent,
  deactivateEmployeeComponent,
  fetchEmployeeComponents,
  fetchSalaryProfiles,
  paymentMethodLabel,
} from '@/payroll/api/employeeSalary'
import { SetSalaryProfileModal } from './components/SetSalaryProfileModal'
import { AssignComponentModal } from './components/AssignComponentModal'

type Selected = { id: number; name: string; no: string }

/**
 * راتب الموظف (Phase 2 / PR-3b) — اختيار موظف ثم إدارة ملف راتبه الأساسي
 * وإسناد المكوّنات، من نقاط النهاية الموجودة فقط. لا حساب/تشغيل/اعتماد.
 */
export function PayrollSalaryPage() {
  const [selected, setSelected] = useState<Selected | null>(null)

  return (
    <div className="space-y-5">
      <PageHeader title="راتب الموظف" subtitle="الراتب الأساسي والمكوّنات المسندة" />

      {selected ? (
        <EmployeeSalaryPanel employee={selected} onChange={() => setSelected(null)} />
      ) : (
        <EmployeePicker onPick={setSelected} />
      )}
    </div>
  )
}

function EmployeePicker({ onPick }: { onPick: (s: Selected) => void }) {
  const [search, setSearch] = useState('')
  const query = useQuery({
    queryKey: ['payroll', 'salary-employee-search', search],
    queryFn: () => fetchEmployees({ search: search.trim(), perPage: 10 }),
    enabled: search.trim().length >= 1,
  })

  return (
    <Card className="space-y-3">
      <label className="block text-sm font-medium text-slate-700">
        ابحث عن موظف
        <input
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="الاسم أو الرقم الوظيفي أو الرقم الوطني"
          className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
        />
      </label>
      {search.trim().length === 0 ? (
        <p className="text-sm text-slate-400">اكتب للبحث عن موظف لإدارة راتبه.</p>
      ) : query.isPending ? (
        <Skeleton className="h-16 w-full" />
      ) : query.isError ? (
        <ErrorState error={query.error} />
      ) : query.data.items.length === 0 ? (
        <EmptyState message="لا يوجد موظف مطابق." />
      ) : (
        <ul className="space-y-2">
          {query.data.items.map((e: EmployeeListItem) => (
            <li key={e.id}>
              <button
                type="button"
                onClick={() => onPick({ id: e.id, name: e.full_name_ar, no: e.employee_no })}
                className="lp-press flex w-full items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-right hover:bg-brand-50"
              >
                <span className="font-medium text-slate-800">{e.full_name_ar}</span>
                <span className="tabular-nums text-xs text-slate-400">{e.employee_no}</span>
              </button>
            </li>
          ))}
        </ul>
      )}
    </Card>
  )
}

function EmployeeSalaryPanel({ employee, onChange }: { employee: Selected; onChange: () => void }) {
  const [settingProfile, setSettingProfile] = useState(false)
  const [assigning, setAssigning] = useState(false)

  const profiles = useQuery({ queryKey: ['payroll', 'salary-profiles', employee.id], queryFn: () => fetchSalaryProfiles(employee.id) })
  const components = useQuery({ queryKey: ['payroll', 'employee-components', employee.id], queryFn: () => fetchEmployeeComponents(employee.id) })

  const active = profiles.data?.find((p) => p.is_active)

  return (
    <div className="space-y-5">
      <Card className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <div className="font-bold text-brand-700">{employee.name}</div>
          <div className="tabular-nums text-xs text-slate-400">{employee.no}</div>
        </div>
        <Button variant="ghost" onClick={onChange}>تغيير الموظف</Button>
      </Card>

      <SectionCard title="الراتب الأساسي" action={<Button onClick={() => setSettingProfile(true)}>تحديث الراتب الأساسي</Button>}>
        {profiles.isPending ? (
          <Skeleton className="h-12 w-full" />
        ) : profiles.isError ? (
          <ErrorState error={profiles.error} />
        ) : !active ? (
          <EmptyState message="لا يوجد ملف راتب نشط. حدّد الراتب الأساسي للبدء." />
        ) : (
          <div className="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
            <Info label="الأساسي" value={formatCurrency(active.basic_salary, active.currency)} />
            <Info label="العملة" value={active.currency} />
            <Info label="طريقة الدفع" value={paymentMethodLabel(active.payment_method)} />
            <Info label="ساري من" value={active.effective_from ?? '—'} />
          </div>
        )}
        {profiles.data && profiles.data.length > 1 && (
          <div className="mt-3 border-t border-slate-100 pt-2 text-xs text-slate-400">
            سجلّ سابق: {profiles.data.length - 1} ملف مؤرشف.
          </div>
        )}
      </SectionCard>

      <SectionCard title="المكوّنات المسندة" action={<Button onClick={() => setAssigning(true)}>إسناد مكوّن</Button>}>
        {components.isPending ? (
          <Skeleton className="h-12 w-full" />
        ) : components.isError ? (
          <ErrorState error={components.error} />
        ) : components.data.length === 0 ? (
          <EmptyState message="لا توجد مكوّنات مسندة لهذا الموظف." />
        ) : (
          <ul className="space-y-2.5">
            {components.data.map((a) => <AssignmentRow key={a.id} assignment={a} employeeId={employee.id} />)}
          </ul>
        )}
      </SectionCard>

      {settingProfile && <SetSalaryProfileModal employeeId={employee.id} onClose={() => setSettingProfile(false)} />}
      {assigning && <AssignComponentModal employeeId={employee.id} onClose={() => setAssigning(false)} />}
    </div>
  )
}

function AssignmentRow({ assignment, employeeId }: { assignment: EmployeeComponent; employeeId: number }) {
  const qc = useQueryClient()
  const { show } = useToast()
  const [confirming, setConfirming] = useState(false)
  const isPct = assignment.component?.value_type === 'percentage'

  const deactivate = useMutation({
    mutationFn: () => deactivateEmployeeComponent(assignment.id),
    onSuccess: () => {
      show('تم إيقاف الإسناد')
      void qc.invalidateQueries({ queryKey: ['payroll', 'employee-components', employeeId] })
    },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّر الإيقاف', 'error'),
  })

  return (
    <li>
      <Card className="flex flex-col gap-3 p-3.5 sm:flex-row sm:items-center sm:justify-between">
        <div className="min-w-0">
          <div className="font-medium text-slate-800">{assignment.component?.name ?? '—'}</div>
          <div className="mt-0.5 text-xs tabular-nums text-slate-400">
            {isPct ? `${assignment.value}%` : formatCurrency(assignment.value, 'SAR')}
            {assignment.effective_from ? ` · من ${assignment.effective_from}` : ''}
          </div>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          {assignment.component && <Badge tone={componentTypeTone(assignment.component.type)}>{componentTypeLabel(assignment.component.type)}</Badge>}
          {assignment.is_active ? (
            confirming ? (
              <span className="flex items-center gap-1.5">
                <Button variant="ghost" onClick={() => deactivate.mutate()} disabled={deactivate.isPending}>تأكيد</Button>
                <Button variant="ghost" onClick={() => setConfirming(false)} disabled={deactivate.isPending}>إلغاء</Button>
              </span>
            ) : (
              <Button variant="ghost" onClick={() => setConfirming(true)}>إيقاف</Button>
            )
          ) : (
            <Badge tone="slate">موقوف</Badge>
          )}
        </div>
      </Card>
    </li>
  )
}

function Info({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <div className="text-xs text-slate-400">{label}</div>
      <div className="font-medium text-slate-800">{value}</div>
    </div>
  )
}
