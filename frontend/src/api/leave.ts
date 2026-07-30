import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { z } from 'zod'
import { api } from './client'

const balanceSchema = z.object({
  year: z.number(),
  total_remaining: z.number(),
  by_type: z.array(
    z.object({
      leave_type_id: z.number(),
      type: z.string().nullable(),
      entitled: z.number(),
      consumed: z.number(),
      remaining: z.number(),
    }),
  ),
})
export type LeaveBalance = z.infer<typeof balanceSchema>

const requestSchema = z.object({
  id: z.number(),
  type: z.string().nullable(),
  start_date: z.string(),
  end_date: z.string(),
  days: z.number(),
  status: z.string(),
  reason: z.string().nullable(),
})
export type LeaveRequest = z.infer<typeof requestSchema>

export interface SubmitLeaveInput {
  leave_type_id: number
  start_date: string
  end_date: string
  reason?: string
}

export function useLeaveBalance() {
  return useQuery({
    queryKey: ['me', 'leave', 'balance'],
    queryFn: async () => balanceSchema.parse(await api.get<unknown>('me/leave/balance')),
  })
}

export function useLeaveRequests() {
  return useQuery({
    queryKey: ['me', 'leave', 'requests'],
    queryFn: async () => z.array(requestSchema).parse(await api.get<unknown>('me/leave/requests')),
  })
}

/** تقديم طلب إجازة (باسم الموظف الحالي حصراً — الباك-إند يتجاهل أي employee_id). */
export function useSubmitLeave() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (input: SubmitLeaveInput) => api.post<{ id: number; status: string; days: number }>('me/leave/requests', input),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['me', 'leave'] })
    },
  })
}
