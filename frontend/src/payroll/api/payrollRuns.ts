import { z } from 'zod'
import { api } from '@/core/api/client'

/* ============================================================
   مسيرات الرواتب (Phase 2 / PR-4) — تشغيل دورة المسير عبر نقاط النهاية الموجودة
   فقط: لقطة حضور/إجازات → احتساب → اعتماد → قفل. لا منطق احتساب/سير جديد؛
   حالات الأزرار تعكس حرّاس الخادم الفعلية (الحالة + وجود السجلّات المحسوبة).
   ============================================================ */

/** الحالات التي يسمح فيها الخادم بالتعديل (لقطة/احتساب/اعتماد). مطابق MUTABLE_STATUSES. */
export const MUTABLE_STATUSES = ['draft', 'processing'] as const
export function isRunMutable(status: string): boolean {
  return (MUTABLE_STATUSES as readonly string[]).includes(status)
}

const RUN_STATUS_LABEL: Record<string, string> = {
  draft: 'مسوّدة',
  processing: 'قيد المعالجة',
  completed: 'مكتملة',
  approved: 'معتمدة',
  paid: 'مدفوعة',
  locked: 'مقفلة',
}
export function runStatusLabel(s: string): string {
  return RUN_STATUS_LABEL[s] ?? s
}
export function runStatusTone(s: string): 'green' | 'amber' | 'slate' | 'navy' {
  if (s === 'locked') return 'navy'
  if (s === 'approved' || s === 'paid') return 'green'
  if (s === 'processing') return 'amber'
  return 'slate'
}

export const runSchema = z.object({
  id: z.number(),
  payroll_period_id: z.number(),
  status: z.string(),
  notes: z.string().nullable().optional(),
  approved_at: z.string().nullable().optional(),
  locked_at: z.string().nullable().optional(),
})
export type PayrollRun = z.infer<typeof runSchema>

export function fetchPeriodRuns(periodId: number): Promise<PayrollRun[]> {
  return api.get<unknown>(`payroll-periods/${periodId}/runs`).then((d) => z.array(runSchema).parse(d ?? []))
}

export function fetchRun(runId: number): Promise<PayrollRun> {
  return api.get<unknown>(`payroll-runs/${runId}`).then((d) => runSchema.parse(d))
}

export function createRun(periodId: number, notes?: string): Promise<PayrollRun> {
  return api.post<unknown>(`payroll-periods/${periodId}/runs`, notes ? { notes } : {}).then((d) => runSchema.parse(d))
}

// ---- إشارات حالة الخطوات (لقطات/احتساب موجودة؟) — نعدّ فقط ----
const countArray = (d: unknown): number => (Array.isArray(d) ? d.length : 0)

export function fetchAttendanceCount(runId: number): Promise<number> {
  return api.get<unknown>(`payroll-runs/${runId}/attendance-summaries`).then(countArray)
}
export function fetchLeaveCount(runId: number): Promise<number> {
  return api.get<unknown>(`payroll-runs/${runId}/leave-summaries`).then(countArray)
}
export function fetchItemsCount(runId: number): Promise<number> {
  return api.getPage<unknown>(`payroll-runs/${runId}/items`).then((env) => countArray(env.data))
}

// ---- أفعال الدورة (كلها POST موجودة) ----
type ActionResult = { payroll_run_id?: number; employees?: number }
export function snapshotAttendance(runId: number): Promise<ActionResult> {
  return api.post<ActionResult>(`payroll-runs/${runId}/attendance-snapshot`)
}
export function snapshotLeave(runId: number): Promise<ActionResult> {
  return api.post<ActionResult>(`payroll-runs/${runId}/leave-snapshot`)
}
export function calculateRun(runId: number): Promise<ActionResult> {
  return api.post<ActionResult>(`payroll-runs/${runId}/calculate`)
}
export function approveRun(runId: number): Promise<PayrollRun> {
  return api.post<unknown>(`payroll-runs/${runId}/approve`).then((d) => runSchema.parse(d))
}
export function lockRun(runId: number): Promise<PayrollRun> {
  return api.post<unknown>(`payroll-runs/${runId}/lock`).then((d) => runSchema.parse(d))
}
