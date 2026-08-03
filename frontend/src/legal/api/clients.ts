import { z } from 'zod'
import { api, apiRequest } from '@/core/api/client'

/* ============================================================
   العملاء — منتقٍ/إضافة سريعة (Phase 3 / PR-2) + إدارة كاملة (Phase 4 / PR-1)
   فوق عقود `clients` الموجودة. لا حذف فعلي على الخادم — التعطيل عبر الحالة.
   ============================================================ */

export const CLIENT_TYPES = ['individual', 'company'] as const
const CLIENT_TYPE_LABEL: Record<string, string> = { individual: 'فرد', company: 'شركة' }
export function clientTypeLabel(t: string): string {
  return CLIENT_TYPE_LABEL[t] ?? t
}

export const CLIENT_STATUSES = ['active', 'inactive'] as const
const CLIENT_STATUS_LABEL: Record<string, string> = { active: 'نشط', inactive: 'معطّل' }
export function clientStatusLabel(s: string): string {
  return CLIENT_STATUS_LABEL[s] ?? s
}
export function clientStatusTone(s: string): 'green' | 'amber' | 'slate' | 'navy' {
  return s === 'active' ? 'green' : 'slate'
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

/* ---- إدارة العملاء الكاملة (Phase 4 / PR-1) ---- */

export const clientSchema = z.object({
  id: z.number(),
  name: z.string(),
  type: z.string(),
  phone: z.string().nullable().optional(),
  email: z.string().nullable().optional(),
  national_id: z.string().nullable().optional(),
  address: z.string().nullable().optional(),
  status: z.string(),
  notes: z.string().nullable().optional(),
})
export type Client = z.infer<typeof clientSchema>

const paginationMetaSchema = z.object({
  page: z.number(),
  per_page: z.number(),
  total: z.number(),
  total_pages: z.number(),
})
export type PaginationMeta = z.infer<typeof paginationMetaSchema>

export interface ClientListParams {
  search?: string
  type?: string
  status?: string
  includeInactive?: boolean
  page?: number
  perPage?: number
}
export interface ClientListResult {
  items: Client[]
  meta: PaginationMeta
}

function buildQuery(p: ClientListParams): string {
  const q = new URLSearchParams()
  if (p.search) q.set('search', p.search)
  if (p.type) q.set('type', p.type)
  if (p.status) q.set('status', p.status)
  else if (p.includeInactive) q.set('include_inactive', '1')
  q.set('page', String(p.page ?? 1))
  q.set('per_page', String(p.perPage ?? 15))
  return q.toString()
}

/** قائمة إدارة العملاء — `GET /clients` (بحث/نوع/حالة/إظهار المعطّلين، مرقّمة). */
export function fetchClientsPage(params: ClientListParams = {}): Promise<ClientListResult> {
  return api.getPage<unknown>(`clients?${buildQuery(params)}`).then((env) => ({
    items: z.array(clientSchema).parse(env.data ?? []),
    meta: paginationMetaSchema.parse(env.meta),
  }))
}

export function fetchClient(id: number): Promise<Client> {
  return api.get<unknown>(`clients/${id}`).then((d) => clientSchema.parse(d))
}

export interface ClientInput {
  name: string
  type: string
  phone?: string | null
  email?: string | null
  national_id?: string | null
  address?: string | null
  notes?: string | null
}

/** إنشاء عميل بنموذج كامل — `POST /clients` (clients.create). */
export function createClientFull(input: ClientInput): Promise<Client> {
  return api.post<unknown>('clients', input).then((d) => clientSchema.parse(d))
}
export function updateClient(id: number, input: Partial<ClientInput>): Promise<Client> {
  return apiRequest<unknown>(`clients/${id}`, { method: 'PUT', body: input }).then((d) => clientSchema.parse(d))
}
/** تفعيل/تعطيل — `PATCH /clients/{id}/status` (بديل الحذف). */
export function setClientStatus(id: number, status: string): Promise<Client> {
  return api.patch<unknown>(`clients/${id}/status`, { status }).then((d) => clientSchema.parse(d))
}
