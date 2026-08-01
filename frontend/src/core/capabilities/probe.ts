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

/**
 * كشف قدرة «إدارة الموارد البشرية» دون تغيير الباك-إند: نداء خفيف لقائمة الموظفين.
 * الخادم يحرسها بصلاحية `employees.view` — فالنجاح (200) يعني أن المستخدم يملكها فعلاً،
 * لا مجرّد وجود المسار. القدرة صلاحية-أوّلاً (HR/مدير/محاسب قد يملكونها لاحقاً باختلاف).
 * - 200 (مصفوفة ولو فارغة) ⇒ يملك الوصول الإداري.
 * - 403 / 401 / خطأ شبكة / data=null ⇒ لا يملكه (تدرّج آمن).
 * إشارة توجيه فقط — الباك-إند يبقى الحكم النهائي على كل عملية.
 */
export async function probeCanManageHr(): Promise<boolean> {
  try {
    const data = await api.get<unknown>('employees?per_page=1')
    return data != null
  } catch {
    return false
  }
}
