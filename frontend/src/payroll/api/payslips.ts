import { z } from 'zod'
import { api } from '@/core/api/client'

/* ============================================================
   كشوف الرواتب (Phase 2 / PR-5) — عرض/طباعة من النتائج المجمّدة فقط.
   لا PDF خادمي ولا إعادة توليد (لا endpoint) — موثّق في docs/BACKLOG.md.
   ============================================================ */

const money = z.union([z.string(), z.number(), z.null()]).optional().transform((v) => (v == null || v === '' ? 0 : Number(v)))

// ---- قائمة كشوف المسير: GET /payroll-runs/{id}/payslips ----
export const payslipRowSchema = z.object({
  payroll_item_id: z.number(),
  employee: z.object({ name: z.string().nullable().optional(), employee_number: z.string().nullable().optional() }).nullable().optional(),
  gross: money,
  deductions_total: money,
  net: money,
  currency: z.string().nullable().optional().transform((v) => v ?? 'SAR'),
})
export type PayslipRow = z.infer<typeof payslipRowSchema>

export function fetchRunPayslips(runId: number): Promise<PayslipRow[]> {
  return api.get<unknown>(`payroll-runs/${runId}/payslips`).then((d) => z.array(payslipRowSchema).parse(d ?? []))
}

// ---- تفاصيل كشف: GET /payroll-items/{id}/payslip ----
const lineSchema = z.object({ name: z.string().nullable().optional().transform((v) => v ?? ''), amount: money })
export const payslipDetailSchema = z.object({
  employee: z.object({ name: z.string().nullable().optional(), employee_number: z.string().nullable().optional() }).nullable().optional(),
  period: z.object({ year: z.number().nullable().optional(), month: z.number().nullable().optional() }).nullable().optional(),
  currency: z.string().nullable().optional().transform((v) => v ?? 'SAR'),
  earnings: z.array(lineSchema).default([]),
  deductions: z.array(lineSchema).default([]),
  gross: money,
  deductions_total: money,
  net: money,
  status: z.string().nullable().optional(),
})
export type PayslipDetail = z.infer<typeof payslipDetailSchema>

export function fetchPayslip(itemId: number): Promise<PayslipDetail> {
  return api.get<unknown>(`payroll-items/${itemId}/payslip`).then((d) => payslipDetailSchema.parse(d))
}

/**
 * طباعة الكشف: يجلب مستند HTML المكتفي ذاتياً من الخادم ويفتح نافذة طباعة.
 * لا مكتبة PDF — نعتمد HTML الجاهز من الخادم (كما في الخدمة الذاتية للموظف).
 */
export async function printPayslip(itemId: number): Promise<void> {
  const html = await api.text(`payroll-items/${itemId}/payslip/html`)
  const win = window.open('', '_blank')
  if (!win) {
    throw new Error('تعذّر فتح نافذة الطباعة. اسمح بالنوافذ المنبثقة لهذا الموقع ثم أعد المحاولة.')
  }
  win.document.open()
  win.document.write(html)
  win.document.close()
  win.focus()
  win.print()
}
