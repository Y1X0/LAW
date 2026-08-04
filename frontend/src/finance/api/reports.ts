import { z } from 'zod'
import { api } from '@/core/api/client'

/**
 * تقارير مالية للقراءة (Phase 6 · PR-10). كل المجاميع من الخادم — الواجهة تعرضها فقط.
 */

const money = z.coerce.number()

export const trialBalanceSchema = z.object({
  accounts: z.array(z.object({
    account_id: z.number(),
    code: z.string(),
    name: z.string(),
    type: z.string(),
    debit: money,
    credit: money,
  })),
  total_debit: money,
  total_credit: money,
})
export type TrialBalance = z.infer<typeof trialBalanceSchema>

export const receivablesSchema = z.object({
  clients: z.array(z.object({
    client_id: z.number(),
    client_name: z.string(),
    invoiced: money,
    paid: money,
    outstanding: money,
  })),
  total_outstanding: money,
})
export type Receivables = z.infer<typeof receivablesSchema>

const accountAmountSchema = z.object({ code: z.string(), name: z.string(), amount: money })

export const incomeExpenseSchema = z.object({
  revenue: money,
  expenses: money,
  net: money,
  revenue_accounts: z.array(accountAmountSchema),
  expense_accounts: z.array(accountAmountSchema),
})
export type IncomeExpense = z.infer<typeof incomeExpenseSchema>

export interface DateRange {
  from?: string
  to?: string
}

function rangeQuery(range: DateRange): string {
  const q = new URLSearchParams()
  if (range.from) q.set('from', range.from)
  if (range.to) q.set('to', range.to)
  const s = q.toString()
  return s ? `?${s}` : ''
}

export function fetchTrialBalance(range: DateRange = {}): Promise<TrialBalance> {
  return api.get<unknown>(`finance/reports/trial-balance${rangeQuery(range)}`).then((d) => trialBalanceSchema.parse(d))
}

export function fetchReceivables(): Promise<Receivables> {
  return api.get<unknown>('finance/reports/receivables').then((d) => receivablesSchema.parse(d))
}

export function fetchIncomeExpense(range: DateRange = {}): Promise<IncomeExpense> {
  return api.get<unknown>(`finance/reports/income-expense${rangeQuery(range)}`).then((d) => incomeExpenseSchema.parse(d))
}
