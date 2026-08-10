import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { formatCurrency, formatDate } from '@/core/lib/format'
import { Badge, Button, Card, SelectField } from '@/core/ui/primitives'
import { PageHeader } from '@/core/ui/section'
import { EmptyState, ErrorState, Skeleton } from '@/core/ui/states'
import { useToast } from '@/core/ui/useToast'
import { useFormErrors } from '@/core/api/useFormErrors'
import { useFinanceCapabilities } from '@/finance/api/capabilities'
import {
  type Expense,
  expenseMethodLabel,
  fetchExpenseCategories,
  fetchExpensesPage,
  reverseExpense,
} from '@/finance/api/expenses'
import { ExpenseFormModal } from './components/ExpenseFormModal'
import { Pagination } from './components/Pagination'

/** قائمة المصروفات (Phase 6 · PR-9) — فلترة بالتصنيف + ترقيم؛ تسجيل/عكس بالصلاحية. */
export function ExpensesListPage() {
  const { canRecordExpense } = useFinanceCapabilities()
  const qc = useQueryClient()
  const { show } = useToast()
  const formErrors = useFormErrors()
  const [categoryId, setCategoryId] = useState('')
  const [page, setPage] = useState(1)
  const [creating, setCreating] = useState(false)

  const categories = useQuery({ queryKey: ['finance', 'expense-categories'], queryFn: fetchExpenseCategories })
  const query = useQuery({
    queryKey: ['finance', 'expenses', { categoryId, page }],
    queryFn: () => fetchExpensesPage({ categoryId: categoryId ? Number(categoryId) : undefined, page }),
  })

  const reverse = useMutation({
    mutationFn: (id: number) => reverseExpense(id),
    onSuccess: () => { show('تم عكس السند'); void qc.invalidateQueries({ queryKey: ['finance', 'expenses'] }) },
    onError: formErrors.onError,
  })

  const items = query.data?.items ?? []
  const reversedIds = new Set(items.map((e) => e.reversal_of_id).filter((v): v is number => v != null))
  const isReversible = (e: Expense) => e.amount > 0 && !reversedIds.has(e.id)

  return (
    <div className="space-y-5">
      <PageHeader
        title="المصروفات"
        subtitle="سندات صرف المكتب"
        action={canRecordExpense ? <Button onClick={() => setCreating(true)}>تسجيل مصروف</Button> : undefined}
      />

      <Card className="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <SelectField label="التصنيف" value={categoryId} onChange={(e) => { setCategoryId(e.target.value); setPage(1) }}>
          <option value="">كل التصنيفات</option>
          {(categories.data ?? []).map((c) => (
            <option key={c.id} value={c.id}>{c.name}</option>
          ))}
        </SelectField>
      </Card>

      {query.isPending ? (
        <Card className="p-0"><div className="space-y-px p-3">
          {Array.from({ length: 8 }).map((_, i) => (
            <div key={i} className="flex items-center gap-4 px-1 py-2.5"><Skeleton className="h-4 w-24" /><Skeleton className="h-4 flex-1" /><Skeleton className="h-4 w-20" /></div>
          ))}
        </div></Card>
      ) : query.isError ? (
        <ErrorState error={query.error}><div className="mt-3"><Button onClick={() => void query.refetch()}>إعادة المحاولة</Button></div></ErrorState>
      ) : items.length === 0 ? (
        <EmptyState message="لا توجد مصروفات مطابقة." />
      ) : (
        <>
          {/* جدول مؤسسي كثيف — يتحوّل إلى بطاقات مكدّسة على الجوّال عبر .lp-table. */}
          <Card className="overflow-hidden p-0">
            <div className="lp-table-wrap">
              <table className={`lp-table text-right text-sm sm:min-w-[760px] ${query.isFetching ? 'opacity-60 transition-opacity' : ''}`} aria-busy={query.isFetching}>
                <thead>
                  <tr>
                    <th className="px-4 py-2.5 text-right">رقم السند</th>
                    <th className="px-4 py-2.5 text-right">التصنيف</th>
                    <th className="px-4 py-2.5 text-right">التاريخ</th>
                    <th className="px-4 py-2.5 text-right">الطريقة</th>
                    <th className="px-4 py-2.5 text-right">المبلغ</th>
                    <th className="px-4 py-2.5 text-right"><span className="sr-only">إجراءات</span></th>
                  </tr>
                </thead>
                <tbody>
                  {items.map((e) => {
                    const reversal = e.amount < 0 || e.reversal_of_id != null
                    return (
                      <tr key={e.id} className="lp-row">
                        <td data-label="رقم السند" className="whitespace-nowrap px-4 py-2.5">
                          <span className="font-medium text-slate-800">{e.voucher_no ?? `#${e.id}`}</span>
                          {reversal && <Badge tone="slate">عكس</Badge>}
                        </td>
                        <td data-label="التصنيف" className="px-4 py-2.5 text-slate-600">{e.category?.name ?? '—'}</td>
                        <td data-label="التاريخ" className="whitespace-nowrap px-4 py-2.5 tabular-nums text-slate-500">{formatDate(e.expense_date ?? null)}</td>
                        <td data-label="الطريقة" className="px-4 py-2.5 text-slate-600">{expenseMethodLabel(e.method)}</td>
                        <td data-label="المبلغ" className="whitespace-nowrap px-4 py-2.5 tabular-nums font-medium text-slate-800">{formatCurrency(e.amount, 'SAR')}</td>
                        <td data-label="" className="px-4 py-2.5 text-left">
                          {canRecordExpense && isReversible(e) && (
                            <Button variant="ghost" onClick={() => reverse.mutate(e.id)} disabled={reverse.isPending}>عكس</Button>
                          )}
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
          </Card>
          <Pagination page={query.data.meta.page} totalPages={query.data.meta.total_pages} onChange={setPage} />
        </>
      )}

      {creating && <ExpenseFormModal onClose={() => setCreating(false)} />}
    </div>
  )
}
