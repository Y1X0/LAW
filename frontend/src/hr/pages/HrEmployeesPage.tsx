import { useState, type FormEvent } from 'react'
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { Badge, Button, Card, Field, SelectField } from '@/core/ui/primitives'
import { PageHeader } from '@/core/ui/section'
import { EmptyState, ErrorState, Skeleton } from '@/core/ui/states'
import { useToast } from '@/core/ui/useToast'
import {
  EMPLOYEE_STATUSES,
  deactivateEmployee,
  employeeStatusLabel,
  employeeStatusTone,
  fetchEmployees,
  type EmployeeListItem,
} from '@/hr/api/employees'

const PER_PAGE = 15

/**
 * إدارة الموظفين (HR-3) — قائمة `GET /employees` مع بحث وفلترة حالة وترقيم خادمي،
 * وفتح ملف الموظف، وتعطيله (أرشفة ناعمة) عبر API الموجود. لا إضافة/تعديل هنا.
 */
export function HrEmployeesPage() {
  const qc = useQueryClient()
  const { show } = useToast()
  const [searchInput, setSearchInput] = useState('')
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const [confirmingId, setConfirmingId] = useState<number | null>(null)

  const { data, isPending, isError, error, refetch, isFetching } = useQuery({
    queryKey: ['hr', 'employees', { search, status, page }],
    queryFn: () => fetchEmployees({ search, status, page, perPage: PER_PAGE }),
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

  function onStatusChange(value: string) {
    setPage(1)
    setStatus(value)
  }

  const hasFilters = search !== '' || status !== ''

  return (
    <div className="space-y-5">
      <PageHeader title="الموظفون" subtitle="إدارة موظفي المكتب" />

      {/* بحث وفلترة */}
      <Card>
        <div className="flex flex-col gap-3 md:flex-row md:items-end">
          <form onSubmit={onSearch} className="flex flex-1 items-end gap-2">
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
          <div className="md:w-52">
            <SelectField label="الحالة" value={status} onChange={(e) => onStatusChange(e.target.value)}>
              <option value="">كل الحالات</option>
              {EMPLOYEE_STATUSES.map((s) => (
                <option key={s} value={s}>
                  {employeeStatusLabel(s)}
                </option>
              ))}
            </SelectField>
          </div>
        </div>
      </Card>

      {isPending ? (
        <EmployeesSkeleton />
      ) : isError ? (
        <ErrorState error={error}>
          <div className="mt-3">
            <Button onClick={() => void refetch()}>إعادة المحاولة</Button>
          </div>
        </ErrorState>
      ) : data.items.length === 0 ? (
        <Card>
          <EmptyState message={hasFilters ? 'لا توجد نتائج مطابقة.' : 'لا يوجد موظفون بعد.'} />
        </Card>
      ) : (
        <>
          <Card className="lp-table-wrap p-0">
            <table
              className={`lp-table min-w-[760px] text-right text-sm transition-opacity ${
                isFetching ? 'opacity-60' : ''
              }`}
              aria-busy={isFetching}
            >
              <thead>
                <tr className="text-xs">
                  <th className="px-4 py-3 text-right">الموظف</th>
                  <th className="px-4 py-3 text-right">الوظيفة</th>
                  <th className="px-4 py-3 text-right">القسم</th>
                  <th className="px-4 py-3 text-right">الحالة</th>
                  <th className="px-4 py-3 text-right">إجراءات</th>
                </tr>
              </thead>
              <tbody>
                {data.items.map((emp) => (
                  <EmployeeRow
                    key={emp.id}
                    emp={emp}
                    confirming={confirmingId === emp.id}
                    deactivating={deactivate.isPending && deactivate.variables === emp.id}
                    onAskConfirm={() => setConfirmingId(emp.id)}
                    onCancelConfirm={() => setConfirmingId(null)}
                    onConfirm={() => deactivate.mutate(emp.id)}
                  />
                ))}
              </tbody>
            </table>
          </Card>

          <div className="flex items-center justify-between gap-3 text-sm text-slate-600">
            <span className="tabular-nums">
              صفحة {data.meta.page} من {data.meta.total_pages} · {data.meta.total} موظف
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

function EmployeeRow({
  emp,
  confirming,
  deactivating,
  onAskConfirm,
  onCancelConfirm,
  onConfirm,
}: {
  emp: EmployeeListItem
  confirming: boolean
  deactivating: boolean
  onAskConfirm: () => void
  onCancelConfirm: () => void
  onConfirm: () => void
}) {
  return (
    <tr className="border-b border-slate-100 last:border-0">
      <td className="px-4 py-3">
        <Link to={`/hr/employees/${emp.id}`} className="font-medium text-brand-700 hover:underline">
          {emp.full_name_ar}
        </Link>
        <div className="tabular-nums text-xs text-slate-400">{emp.employee_no}</div>
      </td>
      <td className="px-4 py-3 text-slate-700">{emp.job_title ?? '—'}</td>
      <td className="px-4 py-3 text-slate-600">{emp.department?.name ?? '—'}</td>
      <td className="px-4 py-3">
        <Badge tone={employeeStatusTone(emp.status)}>{employeeStatusLabel(emp.status)}</Badge>
      </td>
      <td className="px-4 py-3">
        <div className="flex items-center gap-2">
          <Link
            to={`/hr/employees/${emp.id}`}
            className="lp-press rounded-lg px-3 py-1.5 text-sm font-medium text-brand-700 hover:bg-brand-50"
          >
            فتح
          </Link>
          {confirming ? (
            <span className="flex items-center gap-1.5">
              <Button onClick={onConfirm} disabled={deactivating}>
                {deactivating ? 'جارٍ…' : 'تأكيد'}
              </Button>
              <Button variant="ghost" onClick={onCancelConfirm} disabled={deactivating}>
                إلغاء
              </Button>
            </span>
          ) : (
            <Button variant="ghost" onClick={onAskConfirm}>
              تعطيل
            </Button>
          )}
        </div>
      </td>
    </tr>
  )
}

function EmployeesSkeleton() {
  return (
    <Card>
      <div data-testid="employees-skeleton" className="space-y-3">
        {Array.from({ length: 6 }).map((_, i) => (
          <Skeleton key={i} className="h-9 w-full" />
        ))}
      </div>
    </Card>
  )
}
