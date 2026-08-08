import { useState, type ReactNode } from 'react'
import { useQuery } from '@tanstack/react-query'
import { formatCurrency } from '@/core/lib/format'
import { Badge, Button, Card, Field } from '@/core/ui/primitives'
import { PageHeader, SectionCard } from '@/core/ui/section'
import { EmptyState, ErrorState, Skeleton } from '@/core/ui/states'
import {
  fetchIncomeExpense,
  fetchReceivables,
  fetchTrialBalance,
} from '@/finance/api/reports'

type ReportKey = 'trial' | 'receivables' | 'pnl'

const TABS: { key: ReportKey; label: string }[] = [
  { key: 'trial', label: 'ميزان المراجعة' },
  { key: 'receivables', label: 'المستحقات' },
  { key: 'pnl', label: 'الأرباح والخسائر' },
]

function Stat({ label, value, tone = 'slate' }: { label: string; value: string; tone?: 'green' | 'amber' | 'slate' | 'navy' }) {
  const tones: Record<string, string> = {
    green: 'text-green-700',
    amber: 'text-amber-700',
    slate: 'text-slate-800',
    navy: 'text-brand-700',
  }
  return (
    <Card className="p-4">
      <div className="text-xs text-slate-500">{label}</div>
      <div className={`mt-1 text-xl font-bold tabular-nums ${tones[tone]}`}>{value}</div>
    </Card>
  )
}

function ReportBody({ isPending, isError, error, onRetry, children }: { isPending: boolean; isError: boolean; error: unknown; onRetry: () => void; children: ReactNode }) {
  if (isPending) return <Skeleton className="h-40 w-full" />
  if (isError) return <ErrorState error={error}><div className="mt-3"><Button onClick={onRetry}>إعادة المحاولة</Button></div></ErrorState>
  return <>{children}</>
}

/** التقارير المالية (Phase 6 · PR-10) — كل القيم من الخادم؛ الواجهة تعرض فقط. */
export function FinanceReportsPage() {
  const [tab, setTab] = useState<ReportKey>('trial')
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const range = { from: from || undefined, to: to || undefined }

  const trial = useQuery({ queryKey: ['finance', 'report', 'trial', range], queryFn: () => fetchTrialBalance(range), enabled: tab === 'trial' })
  const receivables = useQuery({ queryKey: ['finance', 'report', 'receivables'], queryFn: () => fetchReceivables(), enabled: tab === 'receivables' })
  const pnl = useQuery({ queryKey: ['finance', 'report', 'pnl', range], queryFn: () => fetchIncomeExpense(range), enabled: tab === 'pnl' })

  return (
    <div className="space-y-5">
      <PageHeader title="التقارير المالية" subtitle="ميزان المراجعة · المستحقات · الأرباح والخسائر" />

      <nav aria-label="اختيار التقرير" className="flex gap-1 overflow-x-auto border-b border-slate-200">
        {TABS.map((t) => (
          <button
            key={t.key}
            onClick={() => setTab(t.key)}
            className={`shrink-0 border-b-2 px-4 py-2.5 text-sm transition-colors ${tab === t.key ? 'border-gold-400 font-semibold text-brand-700' : 'border-transparent text-slate-500 hover:text-brand-700'}`}
          >
            {t.label}
          </button>
        ))}
      </nav>

      {tab !== 'receivables' && (
        <Card className="grid grid-cols-1 gap-3 sm:grid-cols-3">
          <Field label="من تاريخ" type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
          <Field label="إلى تاريخ" type="date" value={to} onChange={(e) => setTo(e.target.value)} />
        </Card>
      )}

      {tab === 'trial' && (
        <ReportBody isPending={trial.isPending} isError={trial.isError} error={trial.error} onRetry={() => void trial.refetch()}>
          {trial.data && (
            <>
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <Stat label="إجمالي المدين" value={formatCurrency(trial.data.total_debit, 'SAR')} />
                <Stat label="إجمالي الدائن" value={formatCurrency(trial.data.total_credit, 'SAR')} />
                <Card className="flex items-center p-4">
                  <Badge tone={trial.data.total_debit === trial.data.total_credit ? 'green' : 'amber'}>
                    {trial.data.total_debit === trial.data.total_credit ? 'متوازن ✓' : 'غير متوازن'}
                  </Badge>
                </Card>
              </div>
              <SectionCard title="الحسابات">
                {trial.data.accounts.length === 0 ? <EmptyState message="لا توجد قيود ضمن المدى." /> : (
                  <div className="overflow-x-auto">
                    <table className="lp-table w-full text-sm sm:min-w-[480px]">
                      <thead><tr className="text-right text-xs text-slate-500">
                        <th className="py-1.5 font-medium">الرمز</th><th className="py-1.5 font-medium">الحساب</th>
                        <th className="py-1.5 font-medium">مدين</th><th className="py-1.5 font-medium">دائن</th>
                      </tr></thead>
                      <tbody>
                        {trial.data.accounts.map((a) => (
                          <tr key={a.account_id} className="border-t border-slate-100">
                            <td data-label="الرمز" className="py-1.5 tabular-nums text-slate-500">{a.code}</td>
                            <td data-label="الحساب" className="py-1.5 text-slate-800">{a.name}</td>
                            <td data-label="مدين" className="py-1.5 tabular-nums text-slate-700">{formatCurrency(a.debit, 'SAR')}</td>
                            <td data-label="دائن" className="py-1.5 tabular-nums text-slate-700">{formatCurrency(a.credit, 'SAR')}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </SectionCard>
            </>
          )}
        </ReportBody>
      )}

      {tab === 'receivables' && (
        <ReportBody isPending={receivables.isPending} isError={receivables.isError} error={receivables.error} onRetry={() => void receivables.refetch()}>
          {receivables.data && (
            <>
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <Stat label="إجمالي المستحقات" value={formatCurrency(receivables.data.total_outstanding, 'SAR')} tone="navy" />
              </div>
              <SectionCard title="المستحقات حسب العميل">
                {receivables.data.clients.length === 0 ? <EmptyState message="لا توجد مستحقات." /> : (
                  <div className="overflow-x-auto">
                    <table className="lp-table w-full text-sm sm:min-w-[480px]">
                      <thead><tr className="text-right text-xs text-slate-500">
                        <th className="py-1.5 font-medium">العميل</th><th className="py-1.5 font-medium">مُفوتَر</th>
                        <th className="py-1.5 font-medium">مدفوع</th><th className="py-1.5 font-medium">متبقٍّ</th>
                      </tr></thead>
                      <tbody>
                        {receivables.data.clients.map((c) => (
                          <tr key={c.client_id} className="border-t border-slate-100">
                            <td data-label="العميل" className="py-1.5 text-slate-800">{c.client_name}</td>
                            <td data-label="مُفوتَر" className="py-1.5 tabular-nums text-slate-700">{formatCurrency(c.invoiced, 'SAR')}</td>
                            <td data-label="مدفوع" className="py-1.5 tabular-nums text-green-700">{formatCurrency(c.paid, 'SAR')}</td>
                            <td data-label="متبقٍّ" className="py-1.5 tabular-nums font-semibold text-brand-700">{formatCurrency(c.outstanding, 'SAR')}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </SectionCard>
            </>
          )}
        </ReportBody>
      )}

      {tab === 'pnl' && (
        <ReportBody isPending={pnl.isPending} isError={pnl.isError} error={pnl.error} onRetry={() => void pnl.refetch()}>
          {pnl.data && (
            <>
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <Stat label="الإيرادات" value={formatCurrency(pnl.data.revenue, 'SAR')} tone="green" />
                <Stat label="المصروفات" value={formatCurrency(pnl.data.expenses, 'SAR')} tone="amber" />
                <Stat label="صافي الربح/الخسارة" value={formatCurrency(pnl.data.net, 'SAR')} tone="navy" />
              </div>
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <SectionCard title="الإيرادات حسب الحساب">
                  {pnl.data.revenue_accounts.length === 0 ? <EmptyState message="لا إيرادات." /> : (
                    <ul className="space-y-1.5 text-sm">
                      {pnl.data.revenue_accounts.map((a) => (
                        <li key={a.code} className="flex justify-between"><span className="text-slate-700">{a.name}</span><span className="tabular-nums text-slate-700">{formatCurrency(a.amount, 'SAR')}</span></li>
                      ))}
                    </ul>
                  )}
                </SectionCard>
                <SectionCard title="المصروفات حسب الحساب">
                  {pnl.data.expense_accounts.length === 0 ? <EmptyState message="لا مصروفات." /> : (
                    <ul className="space-y-1.5 text-sm">
                      {pnl.data.expense_accounts.map((a) => (
                        <li key={a.code} className="flex justify-between"><span className="text-slate-700">{a.name}</span><span className="tabular-nums text-slate-700">{formatCurrency(a.amount, 'SAR')}</span></li>
                      ))}
                    </ul>
                  )}
                </SectionCard>
              </div>
            </>
          )}
        </ReportBody>
      )}
    </div>
  )
}
