import { z } from 'zod'
import { api, apiRequest } from '@/core/api/client'

/**
 * واجهة برمجة سندات القبض (Phase 6 · PR-7). المبالغ من الخادم؛ الواجهة تعرضها فقط.
 * التسجيل يمرّر Idempotency-Key (يولّده العميل) لمنع ازدواج التحصيل. العكس لا حذف.
 */

const money = z.coerce.number()

export const paymentSchema = z.object({
  id: z.number(),
  receipt_no: z.string().nullable(),
  invoice_id: z.number(),
  amount: money,
  method: z.string(),
  account_id: z.number(),
  reference: z.string().nullable().optional(),
  payment_date: z.string().nullable().optional(),
  reversal_of_id: z.number().nullable().optional(),
})
export type Payment = z.infer<typeof paymentSchema>

export const financialAccountSchema = z.object({
  id: z.number(),
  name: z.string(),
  type: z.string(),
  currency: z.string().nullable().optional(),
})
export type FinancialAccount = z.infer<typeof financialAccountSchema>

export interface PaymentInput {
  amount: number
  method: string
  account_id: number
  reference?: string | null
  payment_date?: string | null
  notes?: string | null
}

export function fetchInvoicePayments(invoiceId: number): Promise<Payment[]> {
  return api.get<unknown>(`invoices/${invoiceId}/payments`).then((d) => z.array(paymentSchema).parse(d))
}

export function recordPayment(invoiceId: number, input: PaymentInput, idempotencyKey: string): Promise<Payment> {
  return apiRequest<unknown>(`invoices/${invoiceId}/payments`, {
    method: 'POST',
    body: input,
    headers: { 'Idempotency-Key': idempotencyKey },
  }).then((d) => paymentSchema.parse(d))
}

export function reversePayment(paymentId: number): Promise<Payment> {
  return api.post<unknown>(`payments/${paymentId}/reverse`).then((d) => paymentSchema.parse(d))
}

export function fetchFinancialAccounts(): Promise<FinancialAccount[]> {
  return api.get<unknown>('finance/accounts').then((d) => z.array(financialAccountSchema).parse(d))
}

/** مفتاح Idempotency لكل عملية تحصيل مقصودة (يُعاد استخدامه عند إعادة المحاولة). */
export function newIdempotencyKey(): string {
  const c = globalThis.crypto
  if (c && typeof c.randomUUID === 'function') return c.randomUUID()
  return `pay-${Date.now()}-${Math.floor(Math.random() * 1e9)}`
}

export const PAYMENT_METHODS = [
  { value: 'cash', label: 'نقدي' },
  { value: 'bank_transfer', label: 'تحويل بنكي' },
  { value: 'cheque', label: 'شيك' },
  { value: 'card', label: 'بطاقة' },
] as const

const METHOD_LABELS: Record<string, string> = Object.fromEntries(PAYMENT_METHODS.map((m) => [m.value, m.label]))

export function paymentMethodLabel(method: string): string {
  return METHOD_LABELS[method] ?? method
}
