import { Component, type ErrorInfo, type ReactNode } from 'react'
import { captureError } from './sentry'

interface Props {
  children: ReactNode
}

interface State {
  hasError: boolean
}

/**
 * حدّ خطأ عام على مستوى الجذر: يمنع «الشاشة البيضاء» عند أي خطأ عرض غير متوقّع،
 * ويعرض واجهة تعافٍ عربية واضحة، ويُبلِّغ Sentry (خامل ما لم يُضبط الـDSN).
 * لا يبتلع أخطاء المنطق العادية — فقط أخطاء العرض التي كانت ستُعطّل التطبيق كلّياً.
 */
export class ErrorBoundary extends Component<Props, State> {
  state: State = { hasError: false }

  static getDerivedStateFromError(): State {
    return { hasError: true }
  }

  componentDidCatch(error: Error, info: ErrorInfo): void {
    // إبلاغ المراقبة (لا يُرسَل شيء إن لم تُهيّأ Sentry).
    captureError(error)
    // أثر محلي للمطوّر أثناء التطوير.
    if (import.meta.env.DEV) console.error('ErrorBoundary caught:', error, info)
  }

  private handleReload = (): void => {
    window.location.reload()
  }

  render(): ReactNode {
    if (!this.state.hasError) return this.props.children

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
            onClick={this.handleReload}
            className="lp-press mt-5 inline-flex items-center justify-center rounded-md border border-gold-400/60 bg-[#111318] px-5 py-2.5 text-sm font-bold text-white transition hover:bg-black"
          >
            إعادة تحميل الصفحة
          </button>
        </div>
      </div>
    )
  }
}
