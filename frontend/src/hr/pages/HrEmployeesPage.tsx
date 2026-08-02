import { useState, type FormEvent } from 'react'
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { Badge, Button, Card, Field, SelectField } from '@/core/ui/primitives'
import { PageHeader } from '@/core/ui/section'
import { EmptyState, ErrorState, LoadingState, Skeleton } from '@/core/ui/states'
import { useToast } from '@/core/ui/useToast'
import { fetchBranches, fetchDepartments } from '@/core/api/org'
import { EmployeeFormModal } from '@/hr/pages/components/EmployeeFormModal'
import {
  EMPLOYEE_STATUSES,
  type EmployeeDetail,
  type EmployeeListItem,
  deactivateEmployee,
  employeeStatusLabel,
  employeeStatusTone,
  fetchEmployee,
  fetchEmployees,
} from '@/hr/api/employees'

const PER_PAGE = 15

/**
 * إدارة الموظفين (HR-3 + M1/PR-2): قائمة مع بحث وفلترة (فرع/قسم/حالة) وترقيم، وإنشاء
 * وتعديل الموظف من الواجهة، وتعطيل (أرشفة). قائمة بطاقات متجاوبة (تتراص على الجوّال،
 * وتصطفّ أفقياً على سطح المكتب) — بلا جداول عريضة ولا سحب أفقي.
 */
export function HrEmployeesPage() {
  const qc = useQueryClient()
  const { show } = useToast()
  const [searchInput, setSearchInput] = useState('')
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [branchId, setBranchId] = useState<number | ''>('')
  const [departmentId, setDepartmentId] = useState<number | ''>('')
  const [page, setPage] = useState(1)
  const [confirmingId, setConfirmingId] = useState<number | null>(null)
  const [creating, setCreating] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)

  const branches = useQuery({ queryKey: ['org', 'branches'], queryFn: fetchBranches })
  const departments = useQuery({
    queryKey: ['org', 'departments', branchId || 'all'],
    queryFn: () => fetchDepartments(branchId || undefined),
    enabled: branchId !== '',
  })

  const { data, isPending, isError, error, refetch, isFetching } = useQuery({
    queryKey: ['hr', 'employees', { search, status, branchId, departmentId, page }],
    queryFn: () =>
      fetchEmployees({
        search,
        status,
        branchId: branchId || undefined,
        departmentId: departmentId || undefined,
        page,
        perPage: PER_PAGE,
      }),
    placeholderData: keepPreviousData,
  })

  const deactivate = useMutation({
    mutationFn: (id: number) => deactivateEmployee(id),
    onSuccess: () => {
      setConfirmingId(null)
      show('تم تعطيل الموظف')
      void qc.invalidateQueries({ queryKey: ['hr', 'employees'] })
    },
    onError: () => show('تعذّر تعطيل الموظف', 'error'),
  })

  function onSearch(e: FormEvent) {
    e.preventDefault()
    setPage(1)
    setSearch(searchInput.trim())
  }

  const hasFilters = search !== '' || status !== '' || branchId !== '' || departmentId !== ''

  return (
    <div className="space-y-5">
      <PageHeader
        title="الموظفون"
        subtitle="إدارة موظفي المكتب"
        action={<Button onClick={() => setCreating(true)}>إضافة موظف</Button>}
      />

      {/* بحث وفلترة */}
      <Card>
        <div className="grid grid-cols-1 gap-3 md:grid-cols-4">
          <form onSubmit={onSearch} className="flex items-end gap-2 md:col-span-2">
            <div className="flex-1">
              <Field
                label="بحث (الاسم أو الرقم الوظيفي)"
                value={searchInput}
                onChange={(e) => setSearchInput(e.target.value)}
                placeholder="اسم الموظف أو رقمه…"
              />
            </div>
            <Button type="submit">بحث</Button>
          </form>
          <SelectField
            label="الفرع"
            value={branchId === '' ? '' : String(branchId)}
            onChange={(e) => {
              setPage(1)
              setDepartmentId('')
              setBranchId(e.target.value ? Number(e.target.value) : '')
            }}
          >
            <option value="">كل الفروع</option>
            {branches.data?.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
          </SelectField>
          <SelectField
            label="القسم"
            value={departmentId === '' ? '' : String(departmentId)}
            onChange={(e) => { setPage(1); setDepartmentId(e.target.value ? Number(e.target.value) : '') }}
            disabled={branchId === ''}
          >
            <option value="">{branchId === '' ? 'اختر الفرع أولاً' : 'كل الأقسام'}</option>
            {departments.data?.map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}
          </SelectField>
          <SelectField label="الحالة" value={status} onChange={(e) => { setPage(1); setStatus(e.target.value) }}>
            <option value="">كل الحالات</option>
            {EMPLOYEE_STATUSES.map((s) => <option key={s} value={s}>{employeeStatusLabel(s)}</option>)}
          </SelectField>
        </div>
      </Card>

      {isPending ? (
        <EmployeesSkeleton />
      ) : isError ? (
        <ErrorState error={error}>
          <div className="mt-3"><Button onClick={() => void refetch()}>إعادة المحاولة</Button></div>
        </ErrorState>
      ) : data.items.length === 0 ? (
        <Card>
          <EmptyState message={hasFilters ? 'لا توجد نتائج مطابقة.' : 'لا يوجد موظفون بعد. أضِف أول موظف.'} />
        </Card>
      ) : (
        <>
          <ul className={`space-y-2.5 transition-opacity ${isFetching ? 'opacity-60' : ''}`} aria-busy={isFetching}>
            {data.items.map((emp) => (
              <li key={emp.id}>
                <EmployeeCard
                  emp={emp}
                  onEdit={() => setEditingId(emp.id)}
                  confirming={confirmingId === emp.id}
                  deactivating={deactivate.isPending && deactivate.variables === emp.id}
                  onAskConfirm={() => setConfirmingId(emp.id)}
                  onCancelConfirm={() => setConfirmingId(null)}
                  onConfirm={() => deactivate.mutate(emp.id)}
                />
              </li>
            ))}
          </ul>

          <div className="flex items-center justify-between gap-3 text-sm text-slate-600">
            <span className="tabular-nums">
              صفحة {data.meta.page} من {data.meta.total_pages} · {data.meta.total} موظف
            </span>
            <div className="flex gap-2">
              <Button variant="ghost" onClick={() => setPage((p) => Math.max(1, p - 1))} disabled={page <= 1 || isFetching}>السابق</Button>
              <Button variant="ghost" onClick={() => setPage((p) => Math.min(data.meta.total_pages, p + 1))} disabled={page >= data.meta.total_pages || isFetching}>التالي</Button>
            </div>
          </div>
        </>
      )}

      {creating && <EmployeeFormModal employee={null} onClose={() => setCreating(false)} />}
      {editingId !== null && <EditEmployeeModal id={editingId} onClose={() => setEditingId(null)} />}
    </div>
  )
}

/** يجلب تفاصيل الموظف الكاملة ثم يفتح نموذج التعديل. */
function EditEmployeeModal({ id, onClose }: { id: number; onClose: () => void }) {
  const { data, isPending, isError } = useQuery({ queryKey: ['hr', 'employee', id], queryFn: () => fetchEmployee(id) })
  if (isPending) return <LoadingState label="جارٍ تحميل بيانات الموظف…" />
  if (isError || !data) { onClose(); return null }
  return <EmployeeFormModal employee={data as EmployeeDetail} onClose={onClose} />
}

type CardProps = {
  emp: EmployeeListItem
  onEdit: () => void
  confirming: boolean
  deactivating: boolean
  onAskConfirm: () => void
  onCancelConfirm: () => void
  onConfirm: () => void
}

function EmployeeCard({ emp, onEdit, confirming, deactivating, onAskConfirm, onCancelConfirm, onConfirm }: CardProps) {
  return (
    <Card className="flex flex-col gap-3 p-3.5 sm:flex-row sm:items-center sm:justify-between">
      <div className="flex items-start gap-3">
        <div className="min-w-0">
          <Link to={`/hr/employees/${emp.id}`} className="font-bold text-brand-700 hover:underline">{emp.full_name_ar}</Link>
          <div className="tabular-nums text-xs text-slate-400">{emp.employee_no}</div>
          <div className="mt-0.5 text-xs text-slate-500">
            <span>{emp.job_title ?? '—'}</span> · <span>{emp.department?.name ?? '—'}</span>
          </div>
        </div>
      </div>
      <div className="flex flex-wrap items-center gap-1.5">
        <Badge tone={employeeStatusTone(emp.status)}>{employeeStatusLabel(emp.status)}</Badge>
        <Link to={`/hr/employees/${emp.id}`} className="lp-press rounded-lg px-3 py-1.5 text-sm font-medium text-brand-700 hover:bg-brand-50">فتح</Link>
        <Button variant="ghost" onClick={onEdit}>تعديل</Button>
        {confirming ? (
          <span className="flex items-center gap-1.5">
            <Button onClick={onConfirm} disabled={deactivating}>{deactivating ? 'جارٍ…' : 'تأكيد'}</Button>
            <Button variant="ghost" onClick={onCancelConfirm} disabled={deactivating}>إلغاء</Button>
          </span>
        ) : (
          <Button variant="ghost" onClick={onAskConfirm}>تعطيل</Button>
        )}
      </div>
    </Card>
  )
}

function EmployeesSkeleton() {
  return (
    <Card>
      <div data-testid="employees-skeleton" className="space-y-3">
        {Array.from({ length: 6 }).map((_, i) => <Skeleton key={i} className="h-9 w-full" />)}
      </div>
    </Card>
  )
}
