import { z } from 'zod'
import { api } from '@/core/api/client'

/* ============================================================
   واجهة الرواتب (Phase 2 / PR-1) — قراءة من نقاط النهاية الموجودة فقط
   (لا باك-إند جديد). المبالغ العشرية قد تصل كنصّ أو رقم → نوحّدها لرقم.
   ============================================================ */

/** يوحّد قيمة عشرية قد تكون نصّاً أو رقماً أو null إلى number. */
const money = z
  .union([z.string(), z.number(), z.null()])
  .optional()
  .transform((v) => (v == null || v === '' ? 0 : Number(v)))

// ---- 1) ملخّص التكلفة: GET /payroll-reports/cost (من النتائج المجمّدة) ----
export const costTotalsSchema = z.object({
  headcount: z.number().nullable().optional().transform((v) => v ?? 0),
  runs: z.number().nullable().optional().transform((v) => v ?? 0),
  basic: money,
  allowances: money,
  deductions: money,
  gross: money,
  net: money,
})
export type CostTotals = z.infer<typeof costTotalsSchema>

const costReportSchema = z.object({
  totals: costTotalsSchema.nullable().optional(),
})

/** إجماليات الرواتب على مستوى المكتب (كل النتائج المجمّدة، بلا فلتر). */
export function fetchPayrollCostTotals(): Promise<CostTotals> {
  return api.get<unknown>('payroll-reports/cost').then((d) => {
    const parsed = costReportSchema.parse(d)
    return parsed.totals ?? costTotalsSchema.parse({})
  })
}

// ---- 2) أحدث الفترات: GET /payroll-periods?per_page=N ----
export const payrollPeriodSchema = z.object({
  id: z.number(),
  year: z.number(),
  month: z.number(),
  status: z.string(),
  runs_count: z.number().nullable().optional().transform((v) => v ?? 0),
  branch: z.object({ id: z.number(), name: z.string() }).nullable().optional(),
})
export type PayrollPeriodRow = z.infer<typeof payrollPeriodSchema>

/** أحدث فترات الرواتب (للوحة) — مرتّبة تنازلياً من الخادم. */
export function fetchRecentPeriods(limit = 6): Promise<PayrollPeriodRow[]> {
  return api
    .getPage<unknown>(`payroll-periods?per_page=${limit}`)
    .then((env) => z.array(payrollPeriodSchema).parse(env.data ?? []))
}

// ---- أسماء الأشهر (ميلادية) للقوائم والعرض ----
export const MONTHS = [
  'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
  'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر',
] as const

/** اسم الشهر بالميلادي (1..12)؛ يعود للرقم عند خروجه عن النطاق. */
export function monthLabel(month: number): string {
  return MONTHS[month - 1] ?? String(month)
}

// ---- قائمة الفترات المرقّمة + الإنشاء (PR-2) ----
export const PERIOD_STATUSES = ['draft', 'processing', 'approved', 'paid'] as const

const paginationMetaSchema = z.object({
  page: z.number(),
  per_page: z.number(),
  total: z.number(),
  total_pages: z.number(),
})
export type PaginationMeta = z.infer<typeof paginationMetaSchema>

export interface PeriodListParams {
  year?: number
  month?: number
  status?: string
  branchId?: number
  page?: number
  perPage?: number
}

function buildPeriodQuery(params: PeriodListParams): string {
  const q = new URLSearchParams()
  if (params.year) q.set('year', String(params.year))
  if (params.month) q.set('month', String(params.month))
  if (params.status) q.set('status', params.status)
  if (params.branchId) q.set('branch_id', String(params.branchId))
  q.set('page', String(params.page ?? 1))
  q.set('per_page', String(params.perPage ?? 15))
  return q.toString()
}

export interface PeriodListResult {
  items: PayrollPeriodRow[]
  meta: PaginationMeta
}

/** قائمة فترات الرواتب المرقّمة — `GET /payroll-periods` (يحرسها payroll.view). */
export async function fetchPeriods(params: PeriodListParams = {}): Promise<PeriodListResult> {
  const env = await api.getPage<unknown>(`payroll-periods?${buildPeriodQuery(params)}`)
  return {
    items: z.array(payrollPeriodSchema).parse(env.data ?? []),
    meta: paginationMetaSchema.parse(env.meta),
  }
}

export interface CreatePeriodInput {
  year: number
  month: number
  branch_id?: number | null
}

/** إنشاء فترة رواتب — `POST /payroll-periods` (يحرسها payroll.create). */
export async function createPeriod(input: CreatePeriodInput): Promise<PayrollPeriodRow> {
  return payrollPeriodSchema.parse(await api.post<unknown>('payroll-periods', input))
}

// ---- تسميات حالة الفترة (draft/processing/approved/paid) ----
const PERIOD_STATUS_LABEL: Record<string, string> = {
  draft: 'مسوّدة',
  processing: 'قيد المعالجة',
  approved: 'معتمدة',
  paid: 'مدفوعة',
}
export function periodStatusLabel(s: string): string {
  return PERIOD_STATUS_LABEL[s] ?? s
}
export function periodStatusTone(s: string): 'green' | 'amber' | 'slate' | 'navy' {
  if (s === 'approved') return 'green'
  if (s === 'paid') return 'navy'
  if (s === 'processing') return 'amber'
  return 'slate'
}
