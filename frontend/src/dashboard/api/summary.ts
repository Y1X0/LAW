import { z } from 'zod'
import { api } from '@/core/api/client'

/**
 * ملخّص المؤشّرات الإدارية الشاملة — من `GET /api/dashboard/summary` (للقراءة فقط،
 * يحرسه الخادم بصلاحية dashboard.view_management). القيم المالية تصل كسلاسل عشرية
 * ("600.00") فتُحوَّل بـ z.coerce.number؛ الواجهة تعرض فقط ولا تحسب أي مجموع.
 */
const money = z.coerce.number()

export const dashboardSummarySchema = z.object({
  legal: z.object({
    cases_total: z.number(),
    cases_open: z.number(),
    cases_closed: z.number(),
    hearings_upcoming: z.number(),
    tasks_overdue: z.number(),
  }),
  clients: z.object({ active: z.number(), total: z.number() }),
  hr: z.object({ employees_active: z.number() }),
  finance: z.object({
    outstanding: money,
    revenue: money,
    expenses: money,
    net: money,
    invoices_overdue: z.number(),
  }),
})
export type DashboardSummary = z.infer<typeof dashboardSummarySchema>

/** لوحة الإدارة — `GET /api/dashboard/summary` (يحرسها dashboard.view_management). */
export async function fetchDashboardSummary(): Promise<DashboardSummary> {
  return dashboardSummarySchema.parse(await api.get<unknown>('dashboard/summary'))
}
