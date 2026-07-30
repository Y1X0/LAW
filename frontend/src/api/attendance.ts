import { useQuery } from '@tanstack/react-query'
import { z } from 'zod'
import { api } from './client'

const recordSchema = z.object({
  date: z.string(),
  status: z.string(),
  check_in: z.string().nullable(),
  check_out: z.string().nullable(),
  worked_minutes: z.number(),
  late_minutes: z.number(),
  early_leave_minutes: z.number(),
  overtime_minutes: z.number(),
})
export type AttendanceRecord = z.infer<typeof recordSchema>

/** سجلّ حضوري ضمن نطاق تاريخي اختياري (GET /api/me/attendance). */
export function useAttendance(from?: string, to?: string) {
  const query = new URLSearchParams()
  if (from) query.set('from', from)
  if (to) query.set('to', to)
  const qs = query.toString()

  return useQuery({
    queryKey: ['me', 'attendance', { from: from ?? null, to: to ?? null }],
    queryFn: async () =>
      z.array(recordSchema).parse(await api.get<unknown>(`me/attendance${qs ? `?${qs}` : ''}`)),
  })
}
