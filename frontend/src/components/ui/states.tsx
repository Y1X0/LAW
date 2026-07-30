import type { ReactNode } from 'react'
import { ApiError } from '../../api/types'

/** دوّار تحميل. */
export function Spinner({ className = '' }: { className?: string }) {
  return (
    <span
      role="status"
      aria-label="جارٍ التحميل"
      className={`inline-block h-5 w-5 animate-spin rounded-full border-2 border-slate-300 border-t-brand-600 ${className}`}
    />
  )
}

/** تحميل يملأ الصفحة (أثناء التحقّق من الجلسة). */
export function FullPageLoader() {
  return (
    <div className="flex min-h-screen items-center justify-center">
      <Spinner className="h-8 w-8" />
    </div>
  )
}

/** حالة تحميل عامة داخل منطقة محتوى. */
export function LoadingState({ label = 'جارٍ التحميل…' }: { label?: string }) {
  return (
    <div className="flex items-center justify-center gap-2 py-10 text-slate-500">
      <Spinner />
      <span className="text-sm">{label}</span>
    </div>
  )
}

/** حالة فارغة (لا بيانات). */
export function EmptyState({ message = 'لا توجد بيانات لعرضها.' }: { message?: string }) {
  return <div className="py-10 text-center text-sm text-slate-500">{message}</div>
}

/** حالة «لا صلاحية / حساب غير مرتبط» — الباك-إند هو الحكم النهائي. */
export function PermissionDenied({ notLinked = false }: { notLinked?: boolean }) {
  return (
    <div className="rounded-xl border border-amber-200 bg-amber-50 p-6 text-center">
      <p className="text-sm font-medium text-amber-800">
        {notLinked
          ? 'حسابك غير مرتبط بسجلّ موظف. تواصل مع الموارد البشرية.'
          : 'لا تملك صلاحية الوصول إلى هذه الصفحة.'}
      </p>
    </div>
  )
}

/** حالة خطأ عامة — تُميّز 403 (صلاحية/عدم ارتباط) عن بقية الأخطاء. */
export function ErrorState({ error, children }: { error: unknown; children?: ReactNode }) {
  if (error instanceof ApiError && error.isForbidden) {
    return <PermissionDenied notLinked={error.isNotLinkedEmployee} />
  }
  const message = error instanceof Error ? error.message : 'حدث خطأ غير متوقّع.'
  return (
    <div className="rounded-xl border border-red-200 bg-red-50 p-6 text-center">
      <p className="text-sm font-medium text-red-700">{message}</p>
      {children}
    </div>
  )
}
