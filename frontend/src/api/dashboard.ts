import { useQuery } from '@tanstack/react-query'
import { z } from 'zod'
import { api } from './client'

const profileSchema = z.object({
  name: z.string().nullable(),
  employee_number: z.string().nullable(),
  job_title: z.string().nullable(),
  branch: z.string().nullable(),
  department: z.string().nullable(),
  manager: z.string().nullable(),
})

const leaveBalanceSchema = z.object({
  year: z.number(),
  total_remaining: z.number(),
  by_type: z.array(z.object({ type: z.string().nullable(), remaining: z.number() })),
})

const attendanceTodaySchema = z
  .object({
    date: z.string(),
    status: z.string(),
    check_in: z.string().nullable(),
    check_out: z.string().nullable(),
    worked_minutes: z.number(),
    late_minutes: z.number(),
    overtime_minutes: z.number(),
  })
  .nullable()

const lastPayslipSchema = z
  .object({
    payroll_item_id: z.number(),
    year: z.number(),
    month: z.number(),
    net: z.number(),
    currency: z.string(),
  })
  .nullable()

/** مخطط استجابة GET /api/me/dashboard (مطابق لـ MyDashboardService). */
export const dashboardSchema = z.object({
  profile: profileSchema,
  leave_balance: leaveBalanceSchema,
  attendance_today: attendanceTodaySchema,
  last_payslip: lastPayslipSchema,
})

export type Dashboard = z.infer<typeof dashboardSchema>
export type AttendanceToday = z.infer<typeof attendanceTodaySchema>
export type LastPayslip = z.infer<typeof lastPayslipSchema>

export async function fetchDashboard(): Promise<Dashboard> {
  const data = await api.get<unknown>('me/dashboard')
  return dashboardSchema.parse(data)
}

/** جلب لوحة الموظف (نداء واحد مُخزَّن عبر مفتاح ثابت — لا تكرار). */
export function useDashboard() {
  return useQuery({ queryKey: ['me', 'dashboard'], queryFn: fetchDashboard })
}
