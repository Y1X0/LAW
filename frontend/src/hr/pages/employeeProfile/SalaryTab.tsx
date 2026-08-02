import { useQuery } from '@tanstack/react-query'
import { Badge, Card } from '@/core/ui/primitives'
import { AsyncPanel } from '@/hr/components/AsyncPanel'
import { HrDataTable } from '@/hr/components/HrDataTable'
import { fetchEmployeePayroll } from '@/hr/api/employeeProfile'

function money(v: string | number | null | undefined): string {
  if (v == null) return '—'
  return String(v)
}

/**
 * تبويب الرواتب — عرض فقط: GET /payroll-reports/employees/{id}.
 * يتطلّب صلاحية payroll.view — عند غيابها يردّ الخادم 403 فيظهر AsyncPanel
 * حالة «لا تملك صلاحية» تلقائياً (عبر ErrorState).
 */
export function SalaryTab({ employeeId }: { employeeId: string }) {
  const q = useQuery({
    queryKey: ['hr', 'employee', employeeId, 'payroll'],
    queryFn: () => fetchEmployeePayroll(employeeId),
  })

  return (
    <AsyncPanel q={q} isEmpty={(d) => d.history.length === 0} emptyMessage="لا توجد سجلات رواتب لهذا الموظف.">
      {(report) => (
        <div className="space-y-5">
          <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <SummaryTile label="عدد التشغيلات" value={String(report.totals?.runs ?? 0)} />
            <SummaryTile label="الإجمالي" value={money(report.totals?.gross)} />
            <SummaryTile label="الاستقطاعات" value={money(report.totals?.deductions)} />
            <SummaryTile label="الصافي" value={money(report.totals?.net)} accent />
          </div>

          <HrDataTable head={['السنة/الشهر', 'الإجمالي', 'الاستقطاعات', 'الصافي', 'الحالة']}>
            {report.history.map((h) => (
              <tr key={h.payroll_item_id} className="border-b border-slate-100 last:border-0">
                <td data-label="السنة/الشهر" className="px-4 py-3 tabular-nums text-slate-700">
                  {h.year}/{String(h.month).padStart(2, '0')}
                </td>
                <td data-label="الإجمالي" className="px-4 py-3 tabular-nums text-slate-600">{money(h.gross)}</td>
                <td data-label="الاستقطاعات" className="px-4 py-3 tabular-nums text-slate-600">{money(h.deductions)}</td>
                <td data-label="الصافي" className="px-4 py-3 tabular-nums font-medium text-brand-700">{money(h.net)}</td>
                <td data-label="الحالة" className="px-4 py-3">{h.status ? <Badge tone="slate">{h.status}</Badge> : '—'}</td>
              </tr>
            ))}
          </HrDataTable>
        </div>
      )}
    </AsyncPanel>
  )
}

function SummaryTile({ label, value, accent = false }: { label: string; value: string; accent?: boolean }) {
  return (
    <Card className="p-4">
      <div className={`text-xl font-extrabold tabular-nums ${accent ? 'text-brand-700' : 'text-slate-800'}`}>{value}</div>
      <div className="mt-1 text-xs text-slate-500">{label}</div>
    </Card>
  )
}
