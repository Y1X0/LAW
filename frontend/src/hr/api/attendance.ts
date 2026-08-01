import { z } from 'zod'
import { api } from '@/core/api/client'

/** حالات سجلّ الحضور المعتمدة (للفلترة والقيد اليدوي والعرض). */
export const ATTENDANCE_STATUSES = ['present', 'absent', 'late', 'early_leave', 'leave', 'holiday', 'weekend'] as const

const STATUS_LABEL: Record<string, string> = {
  present: 'حاضر',
  absent: 'غائب',
  late: 'متأخر',
  early_leave: 'انصراف مبكر',
  leave: 'إجازة',
  holiday: 'عطلة',
  weekend: 'نهاية أسبوع',
}
export function attendanceStatusLabel(s: string): string {
  return STATUS_LABEL[s] ?? s
}
export function attendanceStatusTone(s: string): 'green' | 'amber' | 'slate' | 'navy' {
  if (s === 'present') return 'green'
  if (s === 'late' || s === 'early_leave') return 'amber'
  if (s === 'absent') return 'navy'
  return 'slate'
}

export const attendanceRecordSchema = z.object({
  id: z.number(),
  work_date: z.string(),
  check_in: z.string().nullable().optional(),
  check_out: z.string().nullable().optional(),
  late_minutes: z.number().nullable().optional(),
  status: z.string(),
  approved_at: z.string().nullable().optional(),
  employee: z
    .object({ id: z.number(), full_name_ar: z.string(), employee_no: z.string().nullable().optional() })
    .nullable()
    .optional(),
})
export type AttendanceRecord = z.infer<typeof attendanceRecordSchema>

const paginationMetaSchema = z.object({
  page: z.number(),
  per_page: z.number(),
  total: z.number(),
  total_pages: z.number(),
})
export type PaginationMeta = z.infer<typeof paginationMetaSchema>

export interface AttendanceListParams {
  date?: string
  status?: string
  page?: number
  perPage?: number
}

function buildQuery(params: AttendanceListParams): string {
  const q = new URLSearchParams()
  if (params.date) q.set('date', params.date)
  if (params.status) q.set('status', params.status)
  q.set('page', String(params.page ?? 1))
  q.set('per_page', String(params.perPage ?? 15))
  return q.toString()
}

export interface AttendanceListResult {
  items: AttendanceRecord[]
  meta: PaginationMeta
}

/** سجلات الحضور — `GET /attendance` (يحرسها attendance.view). */
export async function fetchAttendance(params: AttendanceListParams = {}): Promise<AttendanceListResult> {
  const env = await api.getPage<unknown>(`attendance?${buildQuery(params)}`)
  return {
    items: z.array(attendanceRecordSchema).parse(env.data ?? []),
    meta: paginationMetaSchema.parse(env.meta),
  }
}

export interface ManualAttendanceInput {
  employee_id: number
  work_date: string
  status: string
  check_in?: string | null
  check_out?: string | null
  notes?: string | null
}

/** قيد حضور يدوي — `POST /attendance/manual` (attendance.manual). */
export function createManualAttendance(body: ManualAttendanceInput): Promise<unknown> {
  return api.post<unknown>('attendance/manual', body)
}

/** اعتماد سجلّ حضور — `POST /attendance/{id}/approve` (attendance.approve). */
export function approveAttendance(id: number): Promise<unknown> {
  return api.post<unknown>(`attendance/${id}/approve`)
}
