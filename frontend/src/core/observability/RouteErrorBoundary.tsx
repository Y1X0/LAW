import { useEffect } from 'react'
import type { ReactNode } from 'react'
import { useRouteError } from 'react-router-dom'
import { ErrorFallback } from './ErrorFallback'
import { captureError } from './sentry'

/**
 * حدّ خطأ على مستوى التوجيه (React Router data router). أخطاء عرض الصفحات وأخطاء
 * الـloaders/actions وفشل تحميل chunk كسول يلتقطها الراوتر داخليّاً قبل أن تصل إلى
 * ErrorBoundary الجذري؛ بلا errorElement كان الراوتر يعرض شاشته الافتراضية (إنجليزية،
 * LTR، بلا تعافٍ) ولا يُبلَّغ Sentry. هذا الحدّ يوحّد التجربة: نفس واجهة التعافي العربية،
 * ويُبلِّغ المراقبة (خاملة ما لم يُضبط الـDSN).
 */
export function RouteErrorBoundary(): ReactNode {
  const error = useRouteError()

  useEffect(() => {
    captureError(error)
    if (import.meta.env.DEV) console.error('RouteErrorBoundary caught:', error)
  }, [error])

  return <ErrorFallback />
}
