import type { ToastState } from './useToast'

/** عرض التوست الحالي — ثابت أسفل الشاشة، حركة على transform/opacity. */
export function Toast({ toast }: { toast: ToastState | null }) {
  if (!toast) return null
  const isError = toast.tone === 'error'
  return (
    <div
      role="status"
      aria-live="polite"
      className="lp-toast fixed inset-x-0 bottom-6 z-50 mx-auto flex w-fit max-w-[90vw] items-center gap-2.5 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm shadow-card-hover"
    >
      <span
        aria-hidden="true"
        className={`flex h-6 w-6 items-center justify-center rounded-full ${
          isError ? 'bg-red-50 text-red-600' : 'bg-green-50 text-green-600'
        }`}
      >
        <svg viewBox="0 0 24 24" className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
          {isError ? <path d="M6 6l12 12M18 6L6 18" /> : <path d="M5 13l4 4L19 7" />}
        </svg>
      </span>
      <span className="font-medium text-slate-800">{toast.message}</span>
    </div>
  )
}
