import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ApiError } from '@/core/api/types'
import { formatCurrency, formatDate } from '@/core/lib/format'
import { Badge, Button, Card, SelectField } from '@/core/ui/primitives'
import { PageHeader } from '@/core/ui/section'
import { EmptyState, ErrorState, Skeleton } from '@/core/ui/states'
import { useToast } from '@/core/ui/useToast'
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
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّر العكس', 'error'),
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
        <Skeleton className="h-24 w-full" />
      ) : query.isError ? (
        <ErrorState error={query.error}><div className="mt-3"><Button onClick={() => void query.refetch()}>إعادة المحاولة</Button></div></ErrorState>
      ) : items.length === 0 ? (
        <EmptyState message="لا توجد مصروفات مطابقة." />
      ) : (
        <>
          <ul className="space-y-2.5" aria-busy={query.isFetching}>
            {items.map((e) => {
              const reversal = e.amount < 0 || e.reversal_of_id != null
              return (
                <li key={e.id}>
                  <Card className="flex flex-col gap-2 p-3.5 sm:flex-row sm:items-center sm:justify-between">
                    <div className="min-w-0">
                      <span className="font-medium text-slate-800">{e.voucher_no ?? `#${e.id}`}</span>
                      <div className="mt-0.5 text-xs text-slate-500">
                        {e.category?.name ?? '—'} · {formatDate(e.expense_date ?? null)} · {expenseMethodLabel(e.method)}
                        {reversal && <Badge tone="slate">عكس</Badge>}
                      </div>
                    </div>
                    <span className="flex flex-shrink-0 items-center gap-3">
                      <span className="tabular-nums text-sm font-medium text-slate-700">{formatCurrency(e.amount, 'SAR')}</span>
                      {canRecordExpense && isReversible(e) && (
                        <Button variant="ghost" onClick={() => reverse.mutate(e.id)} disabled={reverse.isPending}>عكس</Button>
                      )}
                    </span>
                  </Card>
                </li>
              )
            })}
          </ul>
          <Pagination page={query.data.meta.page} totalPages={query.data.meta.total_pages} onChange={setPage} />
        </>
      )}

      {creating && <ExpenseFormModal onClose={() => setCreating(false)} />}
    </div>
  )
}
