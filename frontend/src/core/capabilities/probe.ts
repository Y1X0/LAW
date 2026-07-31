import { api } from '@/core/api/client'

/**
 * كشف قدرة «محامٍ» دون تغيير الباك-إند: `GET /api/auth/me` لا يُعيد الصلاحيات،
 * لذا نستنتج الدور من نداء واحد خفيف لملخّص المحامي (يرث حارس الخادم: employee.linked + cases.view_own).
 * - نجاح بـ `data` غير فارغة ⇒ محامٍ.
 * - 403 / 401 / خطأ شبكة / `data=null` ⇒ ليس محامياً (تدرّج آمن إلى بوابة الموظف).
 * الباك-إند يبقى الحكم النهائي؛ هذه إشارة توجيه فقط، لا تُستخدم لعرض بيانات.
 */
export async function probeIsLawyer(): Promise<boolean> {
  try {
    const summary = await api.get<unknown>('me/legal-summary')
    return summary != null
  } catch {
    return false
  }
}
