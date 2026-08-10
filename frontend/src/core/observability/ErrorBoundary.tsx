import { Component, type ErrorInfo, type ReactNode } from 'react'
import { ErrorFallback } from './ErrorFallback'
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

  render(): ReactNode {
    if (!this.state.hasError) return this.props.children

    return <ErrorFallback />
  }
}
