import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Badge, Button, Card, SelectField } from '@/core/ui/primitives'
import { PageHeader, SectionCard } from '@/core/ui/section'
import { EmptyState, ErrorState, Skeleton } from '@/core/ui/states'
import { Tabs } from '@/core/ui/Tabs'
import { formatCurrency } from '@/core/lib/format'
import { fetchBranches, fetchDepartments } from '@/core/api/org'
import { fetchEmployees, type EmployeeListItem } from '@/hr/api/employees'
import { MONTHS, monthLabel } from '@/payroll/api/payroll'
import { runStatusLabel, runStatusTone } from '@/payroll/api/payrollRuns'
import { COST_GROUP_BY, fetchCostReport, fetchEmployeeReport, groupByLabel } from '@/payroll/api/reports'

const CURRENT_YEAR = new Date().getFullYear()
const YEARS = Array.from({ length: 6 }, (_, i) => CURRENT_YEAR - 3 + i)
const STATUSES = ['draft', 'processing', 'completed', 'approved', 'paid', 'locked'] as const

type Tab = 'cost' | 'employee'

/**
 * تقارير الرواتب (Phase 2 / PR-6) — تقرير التكلفة (تجميع فرع/قسم/شهر + فلاتر)
 * وتقرير الموظف، من payroll-reports/* الموجودة فقط. لا احتساب/تصدير جديد.
 */
export function PayrollReportsPage() {
  const [tab, setTab] = useState<Tab>('cost')
  return (
    <div className="space-y-5">
      <PageHeader title="تقارير الرواتب" subtitle="تكلفة الرواتب من النتائج المجمّدة" />
      <Tabs<Tab> tabs={[{ key: 'cost', label: 'تقرير التكلفة' }, { key: 'employee', label: 'تقرير موظف' }]} active={tab} onChange={setTab} />
      {tab === 'cost' ? <CostReportTab /> : <EmployeeReportTab />}
    </div>
  )
}

function Tile({ label, value }: { label: string; value: string }) {
  return (
    <Card className="p-3.5">
      <div className="truncate text-xl font-extrabold tabular-nums text-brand-700">{value}</div>
      <div className="mt-1 text-xs text-slate-500">{label}</div>
    </Card>
  )
}

function CostReportTab() {
  const [year, setYear] = useState('')
  const [month, setMonth] = useState('')
  const [branchId, setBranchId] = useState('')
  const [departmentId, setDepartmentId] = useState('')
  const [status, setStatus] = useState('')
  const [groupBy, setGroupBy] = useState<string>('branch')

  const branches = useQuery({ queryKey: ['org', 'branches'], queryFn: fetchBranches })
  const departments = useQuery({
    queryKey: ['org', 'departments', branchId || 'none'],
    queryFn: () => fetchDepartments(branchId ? Number(branchId) : undefined),
    enabled: branchId !== '',
  })
  const report = useQuery({
    queryKey: ['payroll', 'cost-report', { year, month, branchId, departmentId, status, groupBy }],
    queryFn: () =>
      fetchCostReport({
        year: year ? Number(year) : undefined,
        month: month ? Number(month) : undefined,
        branchId: branchId ? Number(branchId) : undefined,
        departmentId: departmentId ? Number(departmentId) : undefined,
        status: status || undefined,
        groupBy,
      }),
  })

  return (
    <div className="space-y-4">
      <Card className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <SelectField label="السنة" value={year} onChange={(e) => setYear(e.target.value)}>
          <option value="">كل السنوات</option>
          {YEARS.map((y) => <option key={y} value={y}>{y}</option>)}
        </SelectField>
        <SelectField label="الشهر" value={month} onChange={(e) => setMonth(e.target.value)}>
          <option value="">كل الأشهر</option>
          {MONTHS.map((l, i) => <option key={i + 1} value={i + 1}>{l}</option>)}
        </SelectField>
        <SelectField label="الفرع" value={branchId} onChange={(e) => { setBranchId(e.target.value); setDepartmentId('') }}>
          <option value="">كل الفروع</option>
          {branches.data?.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
        </SelectField>
        <SelectField label="القسم" value={departmentId} onChange={(e) => setDepartmentId(e.target.value)} disabled={branchId === ''}>
          <option value="">{branchId === '' ? 'اختر الفرع أولاً' : 'كل الأقسام'}</option>
          {departments.data?.map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}
        </SelectField>
        <SelectField label="الحالة" value={status} onChange={(e) => setStatus(e.target.value)}>
          <option value="">كل الحالات</option>
          {STATUSES.map((s) => <option key={s} value={s}>{runStatusLabel(s)}</option>)}
        </SelectField>
        <SelectField label="التجميع" value={groupBy} onChange={(e) => setGroupBy(e.target.value)}>
          {COST_GROUP_BY.map((g) => <option key={g} value={g}>{groupByLabel(g)}</option>)}
        </SelectField>
      </Card>

      {report.isPending ? (
        <Skeleton className="h-40 w-full" />
      ) : report.isError ? (
        <ErrorState error={report.error}><div className="mt-3"><Button onClick={() => void report.refetch()}>إعادة المحاولة</Button></div></ErrorState>
      ) : (
        <>
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            <Tile label="إجمالي الرواتب" value={formatCurrency(report.data.totals?.gross ?? 0, 'SAR')} />
            <Tile label="الإضافات" value={formatCurrency(report.data.totals?.allowances ?? 0, 'SAR')} />
            <Tile label="الخصومات" value={formatCurrency(report.data.totals?.deductions ?? 0, 'SAR')} />
            <Tile label="الصافي" value={formatCurrency(report.data.totals?.net ?? 0, 'SAR')} />
            <Tile label="الموظفون" value={String(report.data.totals?.headcount ?? 0)} />
            <Tile label="المسيرات" value={String(report.data.totals?.runs ?? 0)} />
          </div>

          <SectionCard title={groupByLabel(groupBy)}>
            {report.data.groups.length === 0 ? (
              <EmptyState message="لا توجد بيانات مطابقة للفلاتر." />
            ) : (
              <ul className="space-y-2.5">
                {report.data.groups.map((g, i) => (
                  <li key={i}>
                    <Card className="flex flex-col gap-2 p-3.5 sm:flex-row sm:items-center sm:justify-between">
                      <div className="font-medium text-slate-800">{g.label}</div>
                      <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm tabular-nums text-slate-600">
                        <span>الموظفون: {g.headcount}</span>
                        <span>الإجمالي: {formatCurrency(g.gross, 'SAR')}</span>
                        <span>الخصومات: {formatCurrency(g.deductions, 'SAR')}</span>
                        <span className="font-bold text-brand-700">الصافي: {formatCurrency(g.net, 'SAR')}</span>
                      </div>
                    </Card>
                  </li>
                ))}
              </ul>
            )}
          </SectionCard>
        </>
      )}
    </div>
  )
}

function EmployeeReportTab() {
  const [selected, setSelected] = useState<{ id: number; name: string } | null>(null)
  const [search, setSearch] = useState('')
  const [year, setYear] = useState('')
  const [status, setStatus] = useState('')

  const results = useQuery({
    queryKey: ['payroll', 'report-emp-search', search],
    queryFn: () => fetchEmployees({ search: search.trim(), perPage: 10 }),
    enabled: !selected && search.trim().length >= 1,
  })
  const report = useQuery({
    queryKey: ['payroll', 'employee-report', selected?.id, { year, status }],
    queryFn: () => fetchEmployeeReport(selected!.id, { year: year ? Number(year) : undefined, status: status || undefined }),
    enabled: !!selected,
  })

  if (!selected) {
    return (
      <Card className="space-y-3">
        <label className="block text-sm font-medium text-slate-700">
          ابحث عن موظف
          <input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="الاسم أو الرقم الوظيفي"
            className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
          />
        </label>
        {search.trim().length === 0 ? (
          <p className="text-sm text-slate-400">اكتب للبحث عن موظف لعرض تقريره.</p>
        ) : results.isPending ? (
          <Skeleton className="h-16 w-full" />
        ) : results.isError ? (
          <ErrorState error={results.error} />
        ) : results.data.items.length === 0 ? (
          <EmptyState message="لا يوجد موظف مطابق." />
        ) : (
          <ul className="space-y-2">
            {results.data.items.map((e: EmployeeListItem) => (
              <li key={e.id}>
                <button type="button" onClick={() => setSelected({ id: e.id, name: e.full_name_ar })} className="lp-press flex w-full items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-right hover:bg-brand-50">
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

  return (
    <div className="space-y-4">
      <Card className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div className="font-bold text-brand-700">{selected.name}</div>
        <div className="flex flex-wrap items-end gap-2">
          <SelectField label="السنة" value={year} onChange={(e) => setYear(e.target.value)}>
            <option value="">كل السنوات</option>
            {YEARS.map((y) => <option key={y} value={y}>{y}</option>)}
          </SelectField>
          <SelectField label="الحالة" value={status} onChange={(e) => setStatus(e.target.value)}>
            <option value="">كل الحالات</option>
            {STATUSES.map((s) => <option key={s} value={s}>{runStatusLabel(s)}</option>)}
          </SelectField>
          <Button variant="ghost" onClick={() => { setSelected(null); setSearch('') }}>تغيير الموظف</Button>
        </div>
      </Card>

      {report.isPending ? (
        <Skeleton className="h-40 w-full" />
      ) : report.isError ? (
        <ErrorState error={report.error}><div className="mt-3"><Button onClick={() => void report.refetch()}>إعادة المحاولة</Button></div></ErrorState>
      ) : (
        <>
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <Tile label="المسيرات" value={String(report.data.totals?.runs ?? 0)} />
            <Tile label="الإجمالي" value={formatCurrency(report.data.totals?.gross ?? 0, 'SAR')} />
            <Tile label="الخصومات" value={formatCurrency(report.data.totals?.deductions ?? 0, 'SAR')} />
            <Tile label="الصافي" value={formatCurrency(report.data.totals?.net ?? 0, 'SAR')} />
          </div>
          <SectionCard title="سجلّ الرواتب">
            {report.data.history.length === 0 ? (
              <EmptyState message="لا توجد سجلّات رواتب مطابقة." />
            ) : (
              <ul className="space-y-2.5">
                {report.data.history.map((h) => (
                  <li key={h.payroll_item_id}>
                    <Card className="flex flex-col gap-2 p-3.5 sm:flex-row sm:items-center sm:justify-between">
                      <div className="font-medium tabular-nums text-slate-800">{monthLabel(h.month)} {h.year}</div>
                      <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm tabular-nums text-slate-600">
                        {h.status && <Badge tone={runStatusTone(h.status)}>{runStatusLabel(h.status)}</Badge>}
                        <span>الخصومات: {formatCurrency(h.deductions, h.currency)}</span>
                        <span className="font-bold text-brand-700">الصافي: {formatCurrency(h.net, h.currency)}</span>
                      </div>
                    </Card>
                  </li>
                ))}
              </ul>
            )}
          </SectionCard>
        </>
      )}
    </div>
  )
}
