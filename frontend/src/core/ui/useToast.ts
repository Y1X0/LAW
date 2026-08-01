import { useCallback, useEffect, useState } from 'react'

export type ToastTone = 'success' | 'error'
export interface ToastState {
  message: string
  tone: ToastTone
}

/**
 * توست بسيط محلّي (بلا مزوّد عام) — يُظهر رسالة نجاح/خطأ مؤقّتة ذاتية الإخفاء.
 * (نظام موحّد أشمل لاحقاً في UP-7؛ هذا كافٍ لتغذية راجعة إجراءات HR.)
 */
export function useToast(duration = 2600): { toast: ToastState | null; show: (message: string, tone?: ToastTone) => void } {
  const [toast, setToast] = useState<ToastState | null>(null)
  const show = useCallback((message: string, tone: ToastTone = 'success') => setToast({ message, tone }), [])
  useEffect(() => {
    if (!toast) return
    const t = window.setTimeout(() => setToast(null), duration)
    return () => window.clearTimeout(t)
  }, [toast, duration])
  return { toast, show }
}
