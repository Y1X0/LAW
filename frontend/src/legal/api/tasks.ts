import { z } from 'zod'
import { api, apiRequest } from '@/core/api/client'

/* ============================================================
   المهام — جانب الإشراف الإداري (Phase 3 / PR-6) فوق عقود `tasks` الموجودة.
   المدير القانوني (tasks.view_all) يرى كل المهام؛ إنشاء/إسناد/إكمال/تعديل عبر
   النقاط المستقلّة. الحالات open/done والأولويات low/normal/high فقط — بلا اختراع.
   ملاحظة: مهام المحامي الذاتية (view_own) مبنيّة أصلاً في بوابة المحامي.
   ============================================================ */

const caseRefSchema = z.object({ id: z.number(), internal_number: z.string(), title: z.string() }).nullable().optional()
const assigneeSchema = z.object({ id: z.number(), full_name_ar: z.string() }).nullable().optional()

export const taskSchema = z.object({
  id: z.number(),
  title: z.string(),
  description: z.string().nullable().optional(),
  priority: z.string(),
  due_date: z.string().nullable().optional(),
  status: z.string(),
  completed_at: z.string().nullable().optional(),
  assigned_to: z.number().nullable().optional(),
  case_id: z.number().nullable().optional(),
  assignee: assigneeSchema,
  case: caseRefSchema,
})
export type Task = z.infer<typeof taskSchema>

const paginationMetaSchema = z.object({
  page: z.number(),
  per_page: z.number(),
  total: z.number(),
  total_pages: z.number(),
})
export type PaginationMeta = z.infer<typeof paginationMetaSchema>

export const TASK_STATUSES = ['open', 'done'] as const
const TASK_STATUS_LABEL: Record<string, string> = { open: 'مفتوحة', done: 'منجزة' }
export function taskStatusLabel(s: string): string {
  return TASK_STATUS_LABEL[s] ?? s
}
export function taskStatusTone(s: string): 'green' | 'amber' | 'slate' | 'navy' {
  return s === 'done' ? 'green' : 'amber'
}

export const TASK_PRIORITIES = ['low', 'normal', 'high'] as const
const TASK_PRIORITY_LABEL: Record<string, string> = { low: 'منخفضة', normal: 'عادية', high: 'عالية' }
export function taskPriorityLabel(p: string): string {
  return TASK_PRIORITY_LABEL[p] ?? p
}
export function taskPriorityTone(p: string): 'green' | 'amber' | 'slate' | 'navy' {
  if (p === 'high') return 'navy'
  if (p === 'low') return 'slate'
  return 'amber'
}

export interface TaskListParams {
  status?: string
  priority?: string
  caseId?: number
  page?: number
  perPage?: number
}
export interface TaskListResult {
  items: Task[]
  meta: PaginationMeta
}

function buildQuery(p: TaskListParams): string {
  const q = new URLSearchParams()
  if (p.status) q.set('status', p.status)
  if (p.priority) q.set('priority', p.priority)
  if (p.caseId) q.set('case_id', String(p.caseId))
  q.set('page', String(p.page ?? 1))
  q.set('per_page', String(p.perPage ?? 15))
  return q.toString()
}

export function fetchTasks(params: TaskListParams = {}): Promise<TaskListResult> {
  return api.getPage<unknown>(`tasks?${buildQuery(params)}`).then((env) => ({
    items: z.array(taskSchema).parse(env.data ?? []),
    meta: paginationMetaSchema.parse(env.meta),
  }))
}

export interface TaskInput {
  title: string
  description?: string | null
  priority?: string
  due_date?: string | null
  case_id?: number | null
}

/** إنشاء مهمة — assigned_to مطلوب على الخادم (StoreTaskRequest). */
export function createTask(input: TaskInput & { assigned_to: number }): Promise<Task> {
  return api.post<unknown>('tasks', input).then((d) => taskSchema.parse(d))
}
/** تعديل مهمة — بلا assigned_to (إعادة الإسناد نقطة مستقلّة). */
export function updateTask(id: number, input: TaskInput): Promise<Task> {
  return apiRequest<unknown>(`tasks/${id}`, { method: 'PUT', body: input }).then((d) => taskSchema.parse(d))
}
export function assignTask(id: number, employeeId: number): Promise<Task> {
  return api.patch<unknown>(`tasks/${id}/assign`, { employee_id: employeeId }).then((d) => taskSchema.parse(d))
}
export function completeTask(id: number): Promise<Task> {
  return api.patch<unknown>(`tasks/${id}/complete`).then((d) => taskSchema.parse(d))
}
