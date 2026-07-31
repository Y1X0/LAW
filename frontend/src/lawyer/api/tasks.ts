import { z } from 'zod'
import { api } from '@/core/api/client'
import { paginationMetaSchema, type PaginationMeta } from './cases'

/**
 * مهام المحامي — المصدر `GET /api/tasks?status=` (بعزل tasks.view_own، مرقّم)
 * و`PATCH /api/tasks/{id}/complete`. قراءة + إكمال فقط.
 */
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
  assignee: assigneeSchema,
  case: caseRefSchema,
})
export type Task = z.infer<typeof taskSchema>

export type TaskStatus = 'open' | 'done'

export interface TaskListResult {
  items: Task[]
  meta: PaginationMeta
}

export const fetchTasks = (status: TaskStatus, page = 1, perPage = 15): Promise<TaskListResult> =>
  api.getPage<unknown>(`tasks?status=${status}&page=${page}&per_page=${perPage}`).then((env) => ({
    items: z.array(taskSchema).parse(env.data ?? []),
    meta: paginationMetaSchema.parse(env.meta),
  }))

export const completeTask = (id: number): Promise<Task> =>
  api.patch<unknown>(`tasks/${id}/complete`).then((d) => taskSchema.parse(d))
