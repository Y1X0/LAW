import { z } from 'zod'
import { api } from '@/core/api/client'

/** نقرأ العدّ الإجمالي من meta.total فقط (per_page=1 كافٍ — لا نحتاج الصفوف). */
const countMetaSchema = z.object({ total: z.number() })

async function count(path: string): Promise<number> {
  const env = await api.getPage<unknown>(path)
  return countMetaSchema.parse(env.meta).total
}

/** تاريخ اليوم بصيغة YYYY-MM-DD (لفلتر غياب اليوم). */
function todayIso(): string {
  return new Date().toISOString().slice(0, 10)
}

export interface HrDashboardStats {
  /** عدد الموظفين — من `GET /employees`. */
  employees: number | null
  /** طلبات إجازة معلّقة — من `GET /leave-requests?status=pending`. */
  pendingLeave: number | null
  /** غياب اليوم — من `GET /attendance?date=<today>&status=absent`. */
  todayAbsent: number | null
}

/**
 * إحصاءات لوحة HR من APIs موجودة فقط (بلا باك-إند جديد) — كلٌّ عبر meta.total.
 * allSettled حتى لا يُسقِط فشلُ مقياسٍ واحد بقيةَ البطاقات (يظهر «—» لذلك المقياس)؛
 * ولا يُرمى خطأ إلا إذا فشلت المقاييس الثلاثة معاً (فتظهر حالة الخطأ العامة).
 */
export async function fetchHrDashboardStats(): Promise<HrDashboardStats> {
  const settled = await Promise.allSettled([
    count('employees?per_page=1'),
    count('leave-requests?status=pending&per_page=1'),
    count(`attendance?status=absent&date=${todayIso()}&per_page=1`),
  ])
  if (settled.every((r) => r.status === 'rejected')) {
    throw (settled[0] as PromiseRejectedResult).reason
  }
  const val = (r: PromiseSettledResult<number>) => (r.status === 'fulfilled' ? r.value : null)
  return { employees: val(settled[0]), pendingLeave: val(settled[1]), todayAbsent: val(settled[2]) }
}
