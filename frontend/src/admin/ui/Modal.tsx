import { useEffect, type ReactNode } from 'react'

/**
 * حوار مركزي بسيط (RTL) — خلفية معتّمة + بطاقة. يُغلق بمفتاح Esc أو النقر خارجه.
 * خفيف بلا اعتماديات؛ يُستخدم لنماذج إدارة المستخدمين (إنشاء/كلمة مرور/ربط).
 */
export function Modal({
  title,
  onClose,
  children,
}: {
  title: string
  onClose: () => void
  children: ReactNode
}) {
  useEffect(() => {
    function onKey(e: KeyboardEvent) {
      if (e.key === 'Escape') onClose()
    }
    document.addEventListener('keydown', onKey)
    return () => document.removeEventListener('keydown', onKey)
  }, [onClose])

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4"
      onMouseDown={onClose}
      role="presentation"
    >
      <div
        className="lp-reveal w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-xl"
        onMouseDown={(e) => e.stopPropagation()}
        role="dialog"
        aria-modal="true"
        aria-label={title}
      >
        <h2 className="mb-4 text-lg font-semibold text-brand-800">{title}</h2>
        {children}
      </div>
    </div>
  )
}
