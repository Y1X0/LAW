import type { ReactNode } from 'react'
import { ApiError } from '@/core/api/types'

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

/** حالة فارغة (لا بيانات) — أنيقة بأيقونة هادئة. */
export function EmptyState({ message = 'لا توجد بيانات لعرضها.' }: { message?: string }) {
  return (
    <div className="lp-reveal flex flex-col items-center justify-center gap-3 py-12 text-center">
      <span
        aria-hidden="true"
        className="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"
      >
        <svg viewBox="0 0 24 24" className="h-7 w-7" fill="none" stroke="currentColor" strokeWidth="1.6">
          <path d="M4 7h5l2 2h9v9a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1z" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
      </span>
      <p className="max-w-xs text-sm text-slate-500">{message}</p>
    </div>
  )
}

/** كتلة هيكلية (Skeleton) بتأثير shimmer محترم أثناء التحميل. */
export function Skeleton({ className = '' }: { className?: string }) {
  return <div className={`lp-skeleton rounded-lg ${className}`} />
}

/** حالة «لا صلاحية / حساب غير مرتبط» — الباك-إند هو الحكم النهائي. */
export function PermissionDenied({ notLinked = false }: { notLinked?: boolean }) {
  return (
    <div className="lp-reveal rounded-2xl border border-amber-200/70 bg-amber-50 p-8 text-center shadow-card">
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
    <div className="lp-reveal rounded-2xl border border-red-200/70 bg-red-50 p-8 text-center shadow-card">
      <p className="text-sm font-medium text-red-700">{message}</p>
      {children}
    </div>
  )
}
