import { useQuery } from '@tanstack/react-query'
import { z } from 'zod'
import { api } from '@/core/api/client'

/** عنصر في قائمة كشوفي (GET /api/me/payslips). */
const payslipListItemSchema = z.object({
  payroll_item_id: z.number(),
  year: z.number(),
  month: z.number(),
  status: z.string(),
  gross: z.number(),
  deductions_total: z.number(),
  net: z.number(),
  currency: z.string(),
})
export type PayslipListItem = z.infer<typeof payslipListItemSchema>

const lineSchema = z.object({ code: z.string(), name: z.string(), amount: z.number() })

/** تفاصيل كشف (GET /api/me/payslips/{id}). */
const payslipDetailSchema = z.object({
  employee: z.object({ name: z.string().nullable(), employee_number: z.string().nullable() }),
  period: z.object({ year: z.number().nullable(), month: z.number().nullable() }),
  currency: z.string(),
  earnings: z.array(lineSchema),
  deductions: z.array(lineSchema),
  gross: z.number(),
  deductions_total: z.number(),
  net: z.number(),
  status: z.string().nullable(),
})
export type PayslipDetail = z.infer<typeof payslipDetailSchema>

/** قائمة كشوفي (المسيّرات النهائية فقط — يفرضها الباك-إند). */
export function usePayslips() {
  return useQuery({
    queryKey: ['me', 'payslips'],
    queryFn: async () => z.array(payslipListItemSchema).parse(await api.get<unknown>('me/payslips')),
  })
}

/** تفاصيل كشف واحد. */
export function usePayslip(id: number) {
  return useQuery({
    queryKey: ['me', 'payslips', id],
    queryFn: async () => payslipDetailSchema.parse(await api.get<unknown>(`me/payslips/${id}`)),
  })
}

/**
 * طباعة الكشف: يجلب مستند HTML المكتفي ذاتياً من الباك-إند (بمصادقة) ويفتح نافذة طباعة.
 * لا مكتبة PDF — نعتمد HTML الجاهز من الباك-إند.
 */
export async function printPayslip(id: number): Promise<void> {
  const html = await api.text(`me/payslips/${id}/html`)
  // نافذة فارغة (نفس الأصل) نكتب فيها مستند الباك-إند ثم نطبع.
  // ملاحظة: لا نمرّر noopener هنا — فهو يُرجع null في المتصفّحات ويمنعنا من الكتابة في النافذة.
  const win = window.open('', '_blank')
  if (!win) {
    // النافذة المنبثقة محجوبة — لا تفشل بصمت؛ ارمِ ليعرض المستدعي رسالة.
    throw new Error('تعذّر فتح نافذة الطباعة. اسمح بالنوافذ المنبثقة لهذا الموقع ثم أعد المحاولة.')
  }
  win.document.open()
  win.document.write(html)
  win.document.close()
  win.focus()
  win.print()
}
