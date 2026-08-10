import type { ReactNode } from 'react'

/**
 * واجهة التعافي العربية الموحّدة عند خطأ عرض غير متوقّع — يتشاركها حدّ الخطأ الجذري
 * (ErrorBoundary) وحدّ خطأ المسارات (RouteErrorBoundary) كي تبقى الرسالة والتنسيق
 * متطابقين مهما كان مصدر الخطأ. زر إعادة التحميل يعيد تشغيل التطبيق من نقطة نظيفة.
 */
export function ErrorFallback(): ReactNode {
  const handleReload = (): void => {
    window.location.reload()
  }

  return (
    <div
      dir="rtl"
      className="flex min-h-screen flex-col items-center justify-center bg-[#f4f5f7] px-6 text-center"
    >
      <div className="w-full max-w-md rounded-xl border border-slate-200 bg-white p-8 shadow-card">
        <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-red-50 text-red-600">
          <svg viewBox="0 0 24 24" className="h-6 w-6" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <path d="M12 9v4M12 17v.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z" />
          </svg>
        </div>
        <h1 className="text-lg font-bold text-slate-900">حدث خطأ غير متوقّع</h1>
        <p className="mt-2 text-sm text-slate-600">
          نعتذر، تعذّر عرض هذه الصفحة. تم تسجيل المشكلة. يمكنك إعادة التحميل والمتابعة.
        </p>
        <button
          onClick={handleReload}
          className="lp-press mt-5 inline-flex items-center justify-center rounded-md border border-gold-400/60 bg-[#111318] px-5 py-2.5 text-sm font-bold text-white transition hover:bg-black"
        >
          إعادة تحميل الصفحة
        </button>
      </div>
    </div>
  )
}
