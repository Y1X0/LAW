import { ApiError } from './types'

/** تصنيف موحّد لأنواع الأخطاء التي قد تظهر للمستخدم. */
export type ApiErrorKind =
  | 'validation' // 422 مع أخطاء حقول
  | 'unauthorized' // 401
  | 'forbidden' // 403
  | 'notfound' // 404
  | 'conflict' // 409
  | 'ratelimited' // 429
  | 'offline' // فشل الشبكة / انقطاع الإنترنت
  | 'timeout' // انتهت مهلة الطلب
  | 'server' // 5xx
  | 'unknown'

/** نتيجة المُخطِّط المركزي: رسالة عامّة للتوست + أخطاء الحقول لعرضها تحت الحقول. */
export interface MappedError {
  /** رسالة عامّة واضحة تُعرض في التوست (لا فشل صامت أبداً). */
  formMessage: string
  /** خطأ واحد واضح لكل حقل (أوّل رسالة) — يُربَط بحقول النموذج. */
  fieldErrors: Record<string, string>
  /** هل الخطأ على مستوى الحقول (422 مع fields)؟ */
  hasFieldErrors: boolean
  status: number | null
  code: string | null
  kind: ApiErrorKind
}

/** رسائل عامّة افتراضية حسب رمز حالة HTTP (عندما لا يوفّر الخادم رسالة). */
const STATUS_FALLBACK: Record<number, string> = {
  400: 'طلب غير صالح. تحقّق من البيانات وحاول مجدداً.',
  401: 'انتهت الجلسة. يرجى تسجيل الدخول من جديد.',
  403: 'لا تملك صلاحية تنفيذ هذا الإجراء.',
  404: 'العنصر المطلوب غير موجود.',
  409: 'تعذّر إتمام العملية بسبب تعارض مع الحالة الحالية.',
  422: 'تحقّق من الحقول المميّزة بالأحمر.',
  429: 'محاولات كثيرة جداً. يرجى المحاولة بعد قليل.',
}

/** رسائل الأخطاء غير المرتبطة باستجابة الخادم (شبكة/مهلة/قراءة). */
const NETWORK_MESSAGE = 'تعذّر الاتصال بالخادم. تحقق من اتصال الإنترنت وحاول مرة أخرى.'
const TIMEOUT_MESSAGE = 'استغرق الطلب وقتاً طويلاً. تحقّق من اتصالك وحاول مرة أخرى.'
const INVALID_RESPONSE_MESSAGE = 'تعذّرت قراءة استجابة الخادم. يرجى المحاولة لاحقاً.'
const GENERIC_MESSAGE = 'تعذّرت العملية. يرجى المحاولة مرة أخرى.'

/** يستخرج أوّل رسالة لكل حقل، ويضيف مفتاح الأب لأي مفتاح متداخل (role_ids.0 → role_ids). */
function flattenFieldErrors(fields: Record<string, string[]>): Record<string, string> {
  const out: Record<string, string> = {}
  for (const [key, messages] of Object.entries(fields)) {
    const first = messages?.[0]
    if (!first) continue
    if (out[key] === undefined) out[key] = first
    // مفتاح الأب لأي مسار متداخل (a.b.c → a) كي يُربَط بحقل النموذج المجمّع.
    const parent = key.split('.')[0]
    if (parent !== key && out[parent] === undefined) out[parent] = first
  }
  return out
}

/**
 * المُخطِّط المركزي الوحيد للأخطاء: يحوّل أي خطأ (ApiError أو خطأ شبكة/مهلة أو غيره)
 * إلى رسالة عامّة واضحة بالعربية + أخطاء حقول قابلة للربط بالنموذج — دون كشف
 * HTML/JSON خام أو تفاصيل داخلية. مصدر الحقيقة الوحيد لعرض الأخطاء في الواجهة.
 */
export function mapApiError(error: unknown): MappedError {
  if (error instanceof ApiError) {
    const { status, code } = error

    // فشل الشبكة/المهلة يصلان كـ ApiError برمز مُوحّد من عميل الـ API.
    if (code === 'NETWORK_ERROR' || status === 0) {
      return base('offline', NETWORK_MESSAGE, status, code)
    }
    if (code === 'TIMEOUT') {
      return base('timeout', TIMEOUT_MESSAGE, status, code)
    }
    // استجابة غير صالحة (HTML بدل JSON مثلاً) — لا نعرض المحتوى الخام أبداً.
    if (code === 'INVALID_JSON') {
      return base('server', INVALID_RESPONSE_MESSAGE, status, code)
    }

    // 422 مع أخطاء حقول → تُعرض تحت الحقول، والرسالة العامّة تُرشِد للتحقّق.
    if (error.fields && Object.keys(error.fields).length > 0) {
      const fieldErrors = flattenFieldErrors(error.fields)
      const formMessage = error.message?.trim() || STATUS_FALLBACK[422]
      return { formMessage, fieldErrors, hasFieldErrors: true, status, code, kind: 'validation' }
    }

    const kind = kindForStatus(status)
    // نُفضّل رسالة الخادم العربية إن وُجدت، وإلا الرسالة الافتراضية حسب الحالة.
    const formMessage = error.message?.trim() || STATUS_FALLBACK[status] || GENERIC_MESSAGE
    return base(kind, formMessage, status, code)
  }

  // أخطاء fetch غير المُطبَّعة (احتياط): TypeError=شبكة، AbortError/TimeoutError=مهلة.
  if (error instanceof DOMException && (error.name === 'AbortError' || error.name === 'TimeoutError')) {
    return base('timeout', TIMEOUT_MESSAGE, 408, 'TIMEOUT')
  }
  if (error instanceof TypeError) {
    return base('offline', NETWORK_MESSAGE, 0, 'NETWORK_ERROR')
  }

  return base('unknown', GENERIC_MESSAGE, null, null)
}

function kindForStatus(status: number): ApiErrorKind {
  switch (status) {
    case 401:
      return 'unauthorized'
    case 403:
      return 'forbidden'
    case 404:
      return 'notfound'
    case 409:
      return 'conflict'
    case 429:
      return 'ratelimited'
    default:
      return status >= 500 ? 'server' : 'unknown'
  }
}

function base(kind: ApiErrorKind, formMessage: string, status: number | null, code: string | null): MappedError {
  return { formMessage, fieldErrors: {}, hasFieldErrors: false, status, code, kind }
}
