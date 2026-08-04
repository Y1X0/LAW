import { z } from 'zod'
import { api } from '@/core/api/client'

/** الملخّص المالي للعميل (Phase 6 · PR-7) — مجاميع محسوبة في الخادم، للعرض فقط. */
const clientFinanceSummarySchema = z.object({
  client_id: z.number(),
  invoice_count: z.number(),
  total_invoiced: z.coerce.number(),
  total_paid: z.coerce.number(),
  outstanding: z.coerce.number(),
})
export type ClientFinanceSummary = z.infer<typeof clientFinanceSummarySchema>

export function fetchClientFinanceSummary(clientId: number): Promise<ClientFinanceSummary> {
  return api.get<unknown>(`finance/clients/${clientId}/summary`).then((d) => clientFinanceSummarySchema.parse(d))
}
