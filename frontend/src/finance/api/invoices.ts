import { z } from 'zod'
import { api, apiRequest } from '@/core/api/client'

/**
 * واجهة برمجة الفواتير (Phase 6 · PR-5). المبالغ تأتي محسوبة من الخادم — الواجهة تعرضها
 * فقط ولا تعيد حسابها. approve/cancel نقاط POST؛ الخادم هو الحكم النهائي على الحالة.
 */

const money = z.coerce.number() // القيم تأتي كنصوص "280.00" من cast decimal — نحوّلها للعرض

export const clientLiteSchema = z.object({ id: z.number(), name: z.string() })

export const invoiceItemSchema = z.object({
  id: z.number(),
  description: z.string(),
  quantity: money,
  unit_price: money,
  tax_rate: money,
  line_total: money,
})
export type InvoiceItem = z.infer<typeof invoiceItemSchema>

export const journalEntryLiteSchema = z.object({
  id: z.number(),
  entry_no: z.string().nullable(),
  entry_date: z.string(),
  description: z.string().nullable().optional(),
  posted: z.boolean(),
})

export const invoiceSchema = z.object({
  id: z.number(),
  invoice_no: z.string().nullable(),
  client_id: z.number(),
  case_id: z.number().nullable().optional(),
  issue_date: z.string().nullable().optional(),
  due_date: z.string().nullable().optional(),
  subtotal: money,
  tax_amount: money,
  discount: money,
  total: money,
  paid_amount: money,
  balance: money,
  status: z.string(),
  notes: z.string().nullable().optional(),
  journal_entry_id: z.number().nullable().optional(),
  approved_at: z.string().nullable().optional(),
  client: clientLiteSchema.nullable().optional(),
  items: z.array(invoiceItemSchema).optional(),
  journalEntry: journalEntryLiteSchema.nullable().optional(),
})
export type Invoice = z.infer<typeof invoiceSchema>

const paginationMetaSchema = z.object({
  page: z.number(),
  per_page: z.number(),
  total: z.number(),
  total_pages: z.number(),
})
export type PaginationMeta = z.infer<typeof paginationMetaSchema>

export interface InvoiceItemInput {
  description: string
  quantity: number
  unit_price: number
  tax_rate: number
}

export interface InvoiceInput {
  client_id: number
  case_id?: number | null
  issue_date?: string | null
  due_date?: string | null
  discount?: number
  notes?: string | null
  items: InvoiceItemInput[]
}

export interface InvoiceListParams {
  search?: string
  status?: string
  clientId?: number
  page?: number
  perPage?: number
}

export interface InvoiceListResult {
  items: Invoice[]
  meta: PaginationMeta
}

function buildQuery(params: InvoiceListParams): string {
  const q = new URLSearchParams()
  if (params.search) q.set('search', params.search)
  if (params.status) q.set('status', params.status)
  if (params.clientId) q.set('client_id', String(params.clientId))
  q.set('page', String(params.page ?? 1))
  q.set('per_page', String(params.perPage ?? 15))
  return q.toString()
}

export function fetchInvoicesPage(params: InvoiceListParams = {}): Promise<InvoiceListResult> {
  return api.getPage<unknown>(`invoices?${buildQuery(params)}`).then((env) => ({
    items: z.array(invoiceSchema).parse(env.data ?? []),
    meta: paginationMetaSchema.parse(env.meta),
  }))
}

export function fetchInvoice(id: number): Promise<Invoice> {
  return api.get<unknown>(`invoices/${id}`).then((d) => invoiceSchema.parse(d))
}

export function createInvoice(input: InvoiceInput): Promise<Invoice> {
  return api.post<unknown>('invoices', input).then((d) => invoiceSchema.parse(d))
}

export function updateInvoice(id: number, input: InvoiceInput): Promise<Invoice> {
  return apiRequest<unknown>(`invoices/${id}`, { method: 'PUT', body: input }).then((d) => invoiceSchema.parse(d))
}

export function approveInvoice(id: number): Promise<Invoice> {
  return api.post<unknown>(`invoices/${id}/approve`).then((d) => invoiceSchema.parse(d))
}

export function cancelInvoice(id: number): Promise<Invoice> {
  return api.post<unknown>(`invoices/${id}/cancel`).then((d) => invoiceSchema.parse(d))
}

/** خيارات العملاء لنموذج الفاتورة (يحرسها clients.view — المحاسب يملكها). */
export function fetchClientOptions(): Promise<{ id: number; name: string }[]> {
  return api.getPage<unknown>('clients?per_page=100').then((env) => z.array(clientLiteSchema).parse(env.data ?? []))
}

const STATUS_LABELS: Record<string, string> = {
  draft: 'مسودّة',
  sent: 'مُرسلة',
  partial: 'مسدّدة جزئياً',
  paid: 'مسدّدة',
  overdue: 'متأخرة',
  cancelled: 'ملغاة',
}
const STATUS_TONES: Record<string, 'green' | 'amber' | 'slate' | 'navy'> = {
  draft: 'slate',
  sent: 'navy',
  partial: 'amber',
  paid: 'green',
  overdue: 'amber',
  cancelled: 'slate',
}

export function invoiceStatusLabel(status: string): string {
  return STATUS_LABELS[status] ?? status
}
export function invoiceStatusTone(status: string): 'green' | 'amber' | 'slate' | 'navy' {
  return STATUS_TONES[status] ?? 'slate'
}
export const INVOICE_STATUSES = ['draft', 'sent', 'partial', 'paid', 'overdue', 'cancelled'] as const
