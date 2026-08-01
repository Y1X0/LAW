import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react'
import { ToastContext, type ToastApi, type ToastItem, type ToastTone } from './toastContext'

const VISIBLE_MS = 2600
const EXIT_MS = 260

type Phase = 'in' | 'out'

/**
 * مزوّد التوست الموحّد لكامل النظام (يُركّب مرّة واحدة في الجذر).
 * توست واحد في كل مرّة، دخول/خروج ناعم، وإخفاء تلقائي — يُستدعى عبر useToast().show().
 */
export function ToastProvider({ children }: { children: ReactNode }) {
  const [state, setState] = useState<{ item: ToastItem; phase: Phase } | null>(null)
  const timers = useRef<number[]>([])

  const clearTimers = useCallback(() => {
    timers.current.forEach((t) => window.clearTimeout(t))
    timers.current = []
  }, [])

  const show = useCallback(
    (message: string, tone: ToastTone = 'success') => {
      clearTimers()
      setState({ item: { message, tone }, phase: 'in' })
      timers.current = [
        window.setTimeout(() => setState((s) => (s ? { ...s, phase: 'out' } : null)), VISIBLE_MS),
        window.setTimeout(() => setState(null), VISIBLE_MS + EXIT_MS),
      ]
    },
    [clearTimers],
  )

  useEffect(() => () => clearTimers(), [clearTimers])

  const api = useMemo<ToastApi>(() => ({ show }), [show])

  return (
    <ToastContext.Provider value={api}>
      {children}
      {state ? <ToastView item={state.item} phase={state.phase} /> : null}
    </ToastContext.Provider>
  )
}

const TONE: Record<ToastTone, { ring: string; icon: string }> = {
  success: { ring: 'bg-green-50 text-green-600', icon: 'M5 13l4 4L19 7' },
  error: { ring: 'bg-red-50 text-red-600', icon: 'M6 6l12 12M18 6L6 18' },
  warning: { ring: 'bg-amber-50 text-amber-600', icon: 'M12 8v5M12 17v.01' },
}

function ToastView({ item, phase }: { item: ToastItem; phase: Phase }) {
  const tone = TONE[item.tone]
  return (
    <div
      role="status"
      aria-live="polite"
      className={`fixed inset-x-0 bottom-6 z-50 mx-auto flex w-fit max-w-[90vw] items-center gap-2.5 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm shadow-card-hover ${
        phase === 'out' ? 'lp-toast-out' : 'lp-toast'
      }`}
    >
      <span aria-hidden="true" className={`flex h-6 w-6 items-center justify-center rounded-full ${tone.ring}`}>
        <svg viewBox="0 0 24 24" className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
          <path d={tone.icon} />
        </svg>
      </span>
      <span className="font-medium text-slate-800">{item.message}</span>
    </div>
  )
}
