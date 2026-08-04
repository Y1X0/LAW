import { z } from 'zod'
import { api } from '@/core/api/client'
import { ApiError } from '@/core/api/types'

/**
 * واجهة برمجة سندات الصرف (Phase 6 · PR-9). المبالغ من الخادم؛ الواجهة تعرضها فقط.
 * العكس لا حذف. ربط القضية اختياري ويخضع لعزل القضايا في الخادم.
 */

const money = z.coerce.number()

const categoryLiteSchema = z.object({ id: z.number(), name: z.string() })

export const expenseSchema = z.object({
  id: z.number(),
  voucher_no: z.string().nullable(),
  category_id: z.number(),
  case_id: z.number().nullable().optional(),
  amount: money,
  method: z.string(),
  account_id: z.number(),
  beneficiary: z.string().nullable().optional(),
  expense_date: z.string().nullable().optional(),
  description: z.string().nullable().optional(),
  reversal_of_id: z.number().nullable().optional(),
  category: categoryLiteSchema.nullable().optional(),
})
export type Expense = z.infer<typeof expenseSchema>

const paginationMetaSchema = z.object({
  page: z.number(),
  per_page: z.number(),
  total: z.number(),
  total_pages: z.number(),
})
export type PaginationMeta = z.infer<typeof paginationMetaSchema>

export interface ExpenseInput {
  category_id: number
  case_id?: number | null
  amount: number
  method: string
  account_id: number
  beneficiary?: string | null
  expense_date?: string | null
  description?: string | null
}

export interface ExpenseListParams {
  categoryId?: number
  caseId?: number
  page?: number
  perPage?: number
}

export interface ExpenseListResult {
  items: Expense[]
  meta: PaginationMeta
}

function buildQuery(params: ExpenseListParams): string {
  const q = new URLSearchParams()
  if (params.categoryId) q.set('category_id', String(params.categoryId))
  if (params.caseId) q.set('case_id', String(params.caseId))
  q.set('page', String(params.page ?? 1))
  q.set('per_page', String(params.perPage ?? 15))
  return q.toString()
}

export function fetchExpensesPage(params: ExpenseListParams = {}): Promise<ExpenseListResult> {
  return api.getPage<unknown>(`expenses?${buildQuery(params)}`).then((env) => ({
    items: z.array(expenseSchema).parse(env.data ?? []),
    meta: paginationMetaSchema.parse(env.meta),
  }))
}

export function createExpense(input: ExpenseInput): Promise<Expense> {
  return api.post<unknown>('expenses', input).then((d) => expenseSchema.parse(d))
}

export function reverseExpense(expenseId: number): Promise<Expense> {
  return api.post<unknown>(`expenses/${expenseId}/reverse`).then((d) => expenseSchema.parse(d))
}

export function fetchExpenseCategories(): Promise<{ id: number; name: string }[]> {
  return api.get<unknown>('finance/expense-categories').then((d) => z.array(categoryLiteSchema).parse(d))
}

export const EXPENSE_METHODS = [
  { value: 'cash', label: 'نقدي' },
  { value: 'bank_transfer', label: 'تحويل بنكي' },
  { value: 'cheque', label: 'شيك' },
] as const

const METHOD_LABELS: Record<string, string> = Object.fromEntries(EXPENSE_METHODS.map((m) => [m.value, m.label]))

export function expenseMethodLabel(method: string): string {
  return METHOD_LABELS[method] ?? method
}

/**
 * خيارات القضايا لربط اختياري. تتحمّل 403 برشاقة (من لا يملك وصول القضايا — كالمحاسب —
 * يحصل على قائمة فارغة فيسجّل مصروفاً بلا قضية). الخادم يفرض العزل عند الربط.
 */
export function fetchCaseOptions(): Promise<{ id: number; title: string }[]> {
  const schema = z.object({ id: z.number(), title: z.string() })
  return api
    .getPage<unknown>('cases?per_page=100')
    .then((env) => z.array(schema).parse(env.data ?? []))
    .catch((e) => {
      if (e instanceof ApiError && (e.isForbidden || e.isUnauthorized)) return []
      throw e
    })
}
