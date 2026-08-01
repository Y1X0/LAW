import { z } from 'zod'
import { api } from '@/core/api/client'

/* ============================================================
   ملف الموظف (HR-4) — قراءة من APIs موجودة فقط. مخطّطات غير صارمة
   (تتجاهل الحقول الإضافية من toArray). كل نداء يرث حراسة الخادم.
   ============================================================ */

// ---- 1) المعلومات: GET /employees/{id} ----
const refSchema = z.object({ id: z.number(), name: z.string() }).nullable().optional()

export const employeeDetailSchema = z.object({
  id: z.number(),
  employee_no: z.string(),
  full_name_ar: z.string(),
  full_name_en: z.string().nullable().optional(),
  national_id: z.string().nullable().optional(),
  gender: z.string().nullable().optional(),
  birth_date: z.string().nullable().optional(),
  phone: z.string().nullable().optional(),
  email: z.string().nullable().optional(),
  address: z.string().nullable().optional(),
  job_title: z.string().nullable().optional(),
  status: z.string(),
  hire_date: z.string().nullable().optional(),
  contract_type: z.string().nullable().optional(),
  emergency_contact_name: z.string().nullable().optional(),
  emergency_contact_phone: z.string().nullable().optional(),
  branch: refSchema,
  department: refSchema,
  manager: z.object({ id: z.number(), full_name_ar: z.string() }).nullable().optional(),
})
export type EmployeeDetail = z.infer<typeof employeeDetailSchema>

export function fetchEmployeeDetail(id: string): Promise<EmployeeDetail> {
  return api.get<unknown>(`employees/${id}`).then((d) => employeeDetailSchema.parse(d))
}

// ---- 2) العقود: GET /employees/{id}/contracts ----
export const contractSchema = z.object({
  id: z.number(),
  contract_type: z.string().nullable().optional(),
  start_date: z.string().nullable().optional(),
  end_date: z.string().nullable().optional(),
  status: z.string().nullable().optional(),
  notes: z.string().nullable().optional(),
})
export type EmployeeContract = z.infer<typeof contractSchema>

export function fetchEmployeeContracts(id: string): Promise<EmployeeContract[]> {
  return api.get<unknown>(`employees/${id}/contracts`).then((d) => z.array(contractSchema).parse(d ?? []))
}

// ---- 3) الوثائق: GET /employees/{id}/documents ----
export const documentSchema = z.object({
  id: z.number(),
  doc_type: z.string().nullable().optional(),
  title: z.string(),
  expiry_date: z.string().nullable().optional(),
  created_at: z.string().nullable().optional(),
})
export type EmployeeDocument = z.infer<typeof documentSchema>

const DOC_TYPE_LABEL: Record<string, string> = {
  contract: 'عقد',
  id_copy: 'صورة هوية',
  certificate: 'شهادة',
  other: 'أخرى',
}
export function docTypeLabel(t: string | null | undefined): string {
  return t ? (DOC_TYPE_LABEL[t] ?? t) : '—'
}

export function fetchEmployeeDocuments(id: string): Promise<EmployeeDocument[]> {
  return api.get<unknown>(`employees/${id}/documents`).then((d) => z.array(documentSchema).parse(d ?? []))
}

// ---- 4) الحضور: GET /attendance?employee_id={id} ----
export const attendanceSchema = z.object({
  id: z.number(),
  work_date: z.string(),
  check_in: z.string().nullable().optional(),
  check_out: z.string().nullable().optional(),
  late_minutes: z.number().nullable().optional(),
  status: z.string(),
})
export type AttendanceRecord = z.infer<typeof attendanceSchema>

const ATT_STATUS_LABEL: Record<string, string> = {
  present: 'حاضر',
  absent: 'غائب',
  late: 'متأخر',
  early_leave: 'انصراف مبكر',
  leave: 'إجازة',
  holiday: 'عطلة',
  weekend: 'نهاية أسبوع',
}
export function attendanceStatusLabel(s: string): string {
  return ATT_STATUS_LABEL[s] ?? s
}
export function attendanceStatusTone(s: string): 'green' | 'amber' | 'slate' | 'navy' {
  if (s === 'present') return 'green'
  if (s === 'late' || s === 'early_leave') return 'amber'
  if (s === 'absent') return 'navy'
  return 'slate'
}

export function fetchEmployeeAttendance(id: string): Promise<AttendanceRecord[]> {
  return api
    .getPage<unknown>(`attendance?employee_id=${id}&per_page=15`)
    .then((env) => z.array(attendanceSchema).parse(env.data ?? []))
}

// ---- 5) الإجازات: GET /employees/{id}/leave-balances + /leave-requests?employee_id={id} ----
export const leaveBalanceSchema = z.object({
  id: z.number(),
  year: z.number(),
  entitled_days: z.number().nullable().optional(),
  consumed_days: z.number().nullable().optional(),
  remaining_days: z.number().nullable().optional(),
  leaveType: z.object({ id: z.number(), name: z.string() }).nullable().optional(),
})
export type LeaveBalance = z.infer<typeof leaveBalanceSchema>

export const leaveRequestSchema = z.object({
  id: z.number(),
  start_date: z.string().nullable().optional(),
  end_date: z.string().nullable().optional(),
  days: z.number().nullable().optional(),
  status: z.string(),
  leaveType: z.object({ id: z.number(), name: z.string() }).nullable().optional(),
})
export type LeaveRequestRow = z.infer<typeof leaveRequestSchema>

const LEAVE_STATUS_LABEL: Record<string, string> = {
  pending: 'معلّقة',
  approved: 'مقبولة',
  rejected: 'مرفوضة',
  cancelled: 'ملغاة',
}
export function leaveStatusLabel(s: string): string {
  return LEAVE_STATUS_LABEL[s] ?? s
}
export function leaveStatusTone(s: string): 'green' | 'amber' | 'slate' | 'navy' {
  if (s === 'approved') return 'green'
  if (s === 'pending') return 'amber'
  if (s === 'rejected') return 'navy'
  return 'slate'
}

export interface EmployeeLeaves {
  balances: LeaveBalance[]
  requests: LeaveRequestRow[]
}

export async function fetchEmployeeLeaves(id: string): Promise<EmployeeLeaves> {
  const [balances, requestsEnv] = await Promise.all([
    api.get<unknown>(`employees/${id}/leave-balances`),
    api.getPage<unknown>(`leave-requests?employee_id=${id}&per_page=15`),
  ])
  return {
    balances: z.array(leaveBalanceSchema).parse(balances ?? []),
    requests: z.array(leaveRequestSchema).parse(requestsEnv.data ?? []),
  }
}

// ---- 6) الرواتب (عرض فقط): GET /payroll-reports/employees/{id} ----
export const payrollHistoryRowSchema = z.object({
  payroll_item_id: z.number(),
  year: z.number(),
  month: z.number(),
  status: z.string().nullable().optional(),
  currency: z.string().nullable().optional(),
  gross: z.union([z.string(), z.number()]).nullable().optional(),
  deductions: z.union([z.string(), z.number()]).nullable().optional(),
  net: z.union([z.string(), z.number()]).nullable().optional(),
})
export const payrollReportSchema = z.object({
  totals: z
    .object({
      runs: z.number().nullable().optional(),
      gross: z.union([z.string(), z.number()]).nullable().optional(),
      deductions: z.union([z.string(), z.number()]).nullable().optional(),
      net: z.union([z.string(), z.number()]).nullable().optional(),
    })
    .nullable()
    .optional(),
  history: z.array(payrollHistoryRowSchema).default([]),
})
export type PayrollReport = z.infer<typeof payrollReportSchema>

export function fetchEmployeePayroll(id: string): Promise<PayrollReport> {
  return api.get<unknown>(`payroll-reports/employees/${id}`).then((d) => payrollReportSchema.parse(d))
}
