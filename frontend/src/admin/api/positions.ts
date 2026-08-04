import { z } from 'zod'
import { api, apiRequest } from '@/core/api/client'

/**
 * إدارة المسمّيات الوظيفية (المناصب) — تعيد استخدام نقاط HR القائمة
 * (قراءة: employees.view · كتابة: employees.update). المدير يملكها ضمناً.
 */
export const positionSchema = z.object({
  id: z.number(),
  branch_id: z.number().nullable().optional(),
  title: z.string(),
  description: z.string().nullable().optional(),
  is_active: z.boolean().optional().default(true),
})
export type Position = z.infer<typeof positionSchema>

export type PositionInput = {
  branch_id?: number | null
  title: string
  description?: string
  is_active?: boolean
}

export async function fetchPositions(branchId?: number): Promise<Position[]> {
  const q = branchId ? `?branch_id=${branchId}` : ''
  return z.array(positionSchema).parse(await api.get<unknown>(`positions${q}`))
}

export async function createPosition(input: PositionInput): Promise<Position> {
  return positionSchema.parse(await api.post<unknown>('positions', input))
}

/** التحديث يشمل العنوان/الوصف/الحالة فقط (الخادم لا يغيّر الفرع بعد الإنشاء). */
export async function updatePosition(id: number, input: Partial<PositionInput>): Promise<Position> {
  return positionSchema.parse(await apiRequest<unknown>(`positions/${id}`, { method: 'PUT', body: input }))
}

export async function deletePosition(id: number): Promise<void> {
  await apiRequest<unknown>(`positions/${id}`, { method: 'DELETE' })
}
