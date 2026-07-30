/** غلاف الاستجابة الموحّد من الباك-إند: { data, meta, errors }. */
export interface ApiEnvelope<T> {
  data: T | null
  meta: unknown
  errors: ApiErrorBody | null
}

export interface ApiErrorBody {
  code: string
  message?: string
  fields?: Record<string, string[]>
}

/** خطأ API موحّد يُرمى من عميل الـ API — يحمل رمز الحالة والرمز الدلالي. */
export class ApiError extends Error {
  constructor(
    public readonly status: number,
    public readonly code: string,
    message: string,
    public readonly fields?: Record<string, string[]>,
  ) {
    super(message)
    this.name = 'ApiError'
  }

  /** المستخدم غير مصادق (توكن منتهٍ/غير صالح). */
  get isUnauthorized(): boolean {
    return this.status === 401
  }

  /** ممنوع: نقص صلاحية أو لا يوجد موظف مرتبط. */
  get isForbidden(): boolean {
    return this.status === 403
  }

  /** الحساب غير مرتبط بسجلّ موظف (حارس الخدمة الذاتية). */
  get isNotLinkedEmployee(): boolean {
    return this.code === 'NO_LINKED_EMPLOYEE'
  }
}
