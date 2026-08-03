import { z } from 'zod'
import { api } from '@/core/api/client'

/* ============================================================
   العملاء — الحدّ الأدنى اللازم لاختيار عميل عند إنشاء القضية + إضافة سريعة
   (Phase 3 / PR-2). ليست شاشة إدارة عملاء — تلك PR مستقلّة لاحقاً.
   ============================================================ */

export const CLIENT_TYPES = ['individual', 'company'] as const
const CLIENT_TYPE_LABEL: Record<string, string> = { individual: 'فرد', company: 'شركة' }
export function clientTypeLabel(t: string): string {
  return CLIENT_TYPE_LABEL[t] ?? t
}

export const clientRefSchema = z.object({
  id: z.number(),
  name: z.string(),
  type: z.string().nullable().optional(),
  status: z.string().nullable().optional(),
})
export type ClientRef = z.infer<typeof clientRefSchema>

/** قائمة العملاء (للاختيار) — `GET /clients` (يحرسها clients.view). */
export async function fetchClients(search?: string): Promise<ClientRef[]> {
  const q = new URLSearchParams()
  if (search) q.set('search', search)
  q.set('per_page', '100')
  const env = await api.getPage<unknown>(`clients?${q.toString()}`)
  return z.array(clientRefSchema).parse(env.data ?? [])
}

export interface QuickClientInput {
  name: string
  type: string
}

/** إضافة عميل سريعة (لإزالة العائق أمام إنشاء القضية) — `POST /clients`. */
export async function createClient(input: QuickClientInput): Promise<ClientRef> {
  return clientRefSchema.parse(await api.post<unknown>('clients', input))
}
