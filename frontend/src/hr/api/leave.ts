import { z } from 'zod'
import { api } from '@/core/api/client'

/** حالات طلب الإجازة المعتمدة (للتبويبات والعرض). */
export const LEAVE_TABS = ['pending', 'approved', 'rejected'] as const
export type LeaveTab = (typeof LEAVE_TABS)[number]

const STATUS_LABEL: Record<string, string> = {
  pending: 'معلّقة',
  approved: 'مقبولة',
  rejected: 'مرفوضة',
  cancelled: 'ملغاة',
}
export function leaveStatusLabel(s: string): string {
  return STATUS_LABEL[s] ?? s
}
export function leaveStatusTone(s: string): 'green' | 'amber' | 'slate' | 'navy' {
  if (s === 'approved') return 'green'
  if (s === 'pending') return 'amber'
  if (s === 'rejected') return 'navy'
  return 'slate'
}

export const leaveRequestSchema = z.object({
  id: z.number(),
  start_date: z.string().nullable().optional(),
  end_date: z.string().nullable().optional(),
  days: z.number().nullable().optional(),
  reason: z.string().nullable().optional(),
  status: z.string(),
  rejection_reason: z.string().nullable().optional(),
  employee: z
    .object({ id: z.number(), full_name_ar: z.string(), employee_no: z.string().nullable().optional() })
    .nullable()
    .optional(),
  leaveType: z.object({ id: z.number(), name: z.string() }).nullable().optional(),
})
export type LeaveRequest = z.infer<typeof leaveRequestSchema>

const paginationMetaSchema = z.object({
  page: z.number(),
  per_page: z.number(),
  total: z.number(),
  total_pages: z.number(),
})
export type PaginationMeta = z.infer<typeof paginationMetaSchema>

export interface LeaveListResult {
  items: LeaveRequest[]
  meta: PaginationMeta
}

/** قائمة طلبات الإجازات — `GET /leave-requests` (يحرسها leaves.view_all). */
export async function fetchLeaveRequests(status: LeaveTab, page = 1, perPage = 15): Promise<LeaveListResult> {
  const env = await api.getPage<unknown>(`leave-requests?status=${status}&page=${page}&per_page=${perPage}`)
  return {
    items: z.array(leaveRequestSchema).parse(env.data ?? []),
    meta: paginationMetaSchema.parse(env.meta),
  }
}

/** موافقة على طلب — `POST /leave-requests/{id}/approve` (leaves.approve). */
export function approveLeave(id: number): Promise<unknown> {
  return api.post<unknown>(`leave-requests/${id}/approve`)
}

/** رفض طلب مع سبب — `POST /leave-requests/{id}/reject` (leaves.approve). */
export function rejectLeave(id: number, reason: string): Promise<unknown> {
  return api.post<unknown>(`leave-requests/${id}/reject`, { reason })
}
