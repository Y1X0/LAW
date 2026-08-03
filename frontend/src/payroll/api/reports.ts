import { z } from 'zod'
import { api } from '@/core/api/client'

/* ============================================================
   تقارير الرواتب (Phase 2 / PR-6) — من النتائج المجمّدة فقط عبر payroll-reports/*.
   لا احتساب/تصدير جديد؛ الأرقام كما يُرجعها الخادم دون إعادة حساب في الواجهة.
   ============================================================ */

const money = z.union([z.string(), z.number(), z.null()]).optional().transform((v) => (v == null || v === '' ? 0 : Number(v)))
const int = z.number().nullable().optional().transform((v) => v ?? 0)

// ---- تقرير التكلفة: GET /payroll-reports/cost ----
export const COST_GROUP_BY = ['branch', 'department', 'month'] as const
const GROUP_BY_LABEL: Record<string, string> = { branch: 'حسب الفرع', department: 'حسب القسم', month: 'حسب الشهر' }
export function groupByLabel(g: string): string {
  return GROUP_BY_LABEL[g] ?? g
}

const costMetricsSchema = z.object({
  headcount: int,
  runs: int,
  basic: money,
  allowances: money,
  deductions: money,
  gross: money,
  net: money,
})
export type CostMetrics = z.infer<typeof costMetricsSchema>

const costGroupSchema = costMetricsSchema.extend({
  label: z.string().nullable().optional().transform((v) => v ?? 'غير محدد'),
})
export type CostGroup = z.infer<typeof costGroupSchema>

export const costReportSchema = z.object({
  totals: costMetricsSchema.nullable().optional(),
  groups: z.array(costGroupSchema).default([]),
})
export type CostReport = z.infer<typeof costReportSchema>

export interface CostFilters {
  year?: number
  month?: number
  branchId?: number
  departmentId?: number
  status?: string
  groupBy?: string
}

export async function fetchCostReport(filters: CostFilters = {}): Promise<CostReport> {
  const q = new URLSearchParams()
  if (filters.year) q.set('year', String(filters.year))
  if (filters.month) q.set('month', String(filters.month))
  if (filters.branchId) q.set('branch_id', String(filters.branchId))
  if (filters.departmentId) q.set('department_id', String(filters.departmentId))
  if (filters.status) q.set('status', filters.status)
  if (filters.groupBy) q.set('group_by', filters.groupBy)
  const qs = q.toString()
  const parsed = costReportSchema.parse(await api.get<unknown>(`payroll-reports/cost${qs ? `?${qs}` : ''}`))
  return { totals: parsed.totals ?? costMetricsSchema.parse({}), groups: parsed.groups }
}

// ---- تقرير الموظف: GET /payroll-reports/employees/{id} ----
export const employeeReportSchema = z.object({
  employee: z.object({ id: z.number(), name: z.string().nullable().optional(), employee_number: z.string().nullable().optional() }).nullable().optional(),
  totals: z.object({ runs: int, gross: money, deductions: money, net: money }).nullable().optional(),
  history: z
    .array(
      z.object({
        payroll_item_id: z.number(),
        year: z.number(),
        month: z.number(),
        status: z.string().nullable().optional(),
        currency: z.string().nullable().optional().transform((v) => v ?? 'SAR'),
        gross: money,
        deductions: money,
        net: money,
      }),
    )
    .default([]),
})
export type EmployeeReport = z.infer<typeof employeeReportSchema>

export async function fetchEmployeeReport(employeeId: number, opts: { year?: number; status?: string } = {}): Promise<EmployeeReport> {
  const q = new URLSearchParams()
  if (opts.year) q.set('year', String(opts.year))
  if (opts.status) q.set('status', opts.status)
  const qs = q.toString()
  return employeeReportSchema.parse(await api.get<unknown>(`payroll-reports/employees/${employeeId}${qs ? `?${qs}` : ''}`))
}
