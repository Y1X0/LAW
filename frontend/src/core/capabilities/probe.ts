import { api } from '@/core/api/client'
import { ApiError } from '@/core/api/types'

/**
 * «لا» حاسمة: 401/403 يعني قطعاً أن المستخدم لا يملك هذه القدرة → false.
 * أمّا أخطاء الشبكة/5xx فليست حسماً — نعيد رميها ليعيد React Query المحاولة،
 * بدل حسم خاطئ يحبس المستخدم في البوابة الخطأ طوال الجلسة.
 */
function isDefinitiveNo(error: unknown): boolean {
  return error instanceof ApiError && (error.status === 401 || error.status === 403)
}

/**
 * كشف قدرة «محامٍ» دون تغيير الباك-إند: نداء واحد خفيف لملخّص المحامي
 * (يرث حارس الخادم: employee.linked + cases.view_own).
 * - نجاح بـ `data` غير فارغة ⇒ محامٍ. · 200 بـ data=null أو 401/403 ⇒ ليس محامياً.
 * - خطأ شبكة/5xx ⇒ يُعاد رميه (إعادة محاولة)، لا حسم.
 */
export async function probeIsLawyer(): Promise<boolean> {
  try {
    const summary = await api.get<unknown>('me/legal-summary')
    return summary != null
  } catch (error) {
    if (isDefinitiveNo(error)) return false
    throw error
  }
}

/**
 * كشف قدرة «إدارة الموارد البشرية»: نداء خفيف لقائمة الموظفين (يحرسها employees.view).
 * - 200 (ولو مصفوفة فارغة) ⇒ يملك الوصول. · 401/403 ⇒ لا يملكه.
 * - خطأ شبكة/5xx ⇒ يُعاد رميه (إعادة محاولة).
 */
export async function probeCanManageHr(): Promise<boolean> {
  try {
    const data = await api.get<unknown>('employees?per_page=1')
    return data != null
  } catch (error) {
    if (isDefinitiveNo(error)) return false
    throw error
  }
}

/**
 * كشف قدرة «مالك المنصّة / Super Admin»: نداء خفيف لقائمة الأدوار (يحرسها roles.manage،
 * غير مبذورة لمحامٍ/موظف/HR). - 200 ⇒ Admin. · 401/403 ⇒ ليس Admin.
 * - خطأ شبكة/5xx ⇒ يُعاد رميه (إعادة محاولة).
 */
export async function probeIsAdmin(): Promise<boolean> {
  try {
    const data = await api.get<unknown>('roles')
    return data != null
  } catch (error) {
    if (isDefinitiveNo(error)) return false
    throw error
  }
}

/**
 * كشف قدرة «إدارة الرواتب»: نداء خفيف لقائمة فترات الرواتب (يحرسها payroll.view).
 * - 200 (ولو مصفوفة فارغة) ⇒ يملك الوصول. · 401/403 ⇒ لا يملكه.
 * - خطأ شبكة/5xx ⇒ يُعاد رميه (إعادة محاولة).
 */
export async function probeCanManagePayroll(): Promise<boolean> {
  try {
    const data = await api.get<unknown>('payroll-periods?per_page=1')
    return data != null
  } catch (error) {
    if (isDefinitiveNo(error)) return false
    throw error
  }
}

/**
 * كشف قدرة «الإدارة القانونية»: نداء خفيف لقائمة العملاء (يحرسها clients.view —
 * صلاحية إدارية لا يملكها المحامي بعزل view_own). يميّز «مشرف/مدير قانوني» عن المحامي.
 * - 200 ⇒ يملك الإدارة القانونية. · 401/403 ⇒ لا يملكها.
 * - خطأ شبكة/5xx ⇒ يُعاد رميه (إعادة محاولة).
 */
export async function probeCanManageLegal(): Promise<boolean> {
  try {
    const data = await api.get<unknown>('clients?per_page=1')
    return data != null
  } catch (error) {
    if (isDefinitiveNo(error)) return false
    throw error
  }
}
