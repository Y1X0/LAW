import { useEffect, useRef, type ReactNode } from 'react'

const FOCUSABLE = 'a[href],button:not([disabled]),textarea,input,select,[tabindex]:not([tabindex="-1"])'

/**
 * حوار مركزي (RTL) — خلفية معتّمة + بطاقة. يُغلق بـ Esc أو النقر خارجه.
 * إتاحة: يُركّز داخل الحوار عند الفتح، يحبس Tab داخله، يعيد التركيز للعنصر السابق
 * عند الإغلاق، ويقفل تمرير الصفحة خلفه. خفيف بلا اعتماديات.
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
  const dialogRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    const previouslyFocused = document.activeElement as HTMLElement | null
    const dialog = dialogRef.current

    // تركيز أوّل عنصر قابل للتركيز داخل الحوار (أو الحوار نفسه).
    const first = dialog?.querySelector<HTMLElement>(FOCUSABLE)
    ;(first ?? dialog)?.focus()

    // قفل تمرير الصفحة خلف الحوار.
    const prevOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'

    function onKey(e: KeyboardEvent) {
      if (e.key === 'Escape') {
        onClose()
        return
      }
      if (e.key !== 'Tab' || !dialog) return
      // حبس التركيز: دوران Tab/Shift+Tab داخل الحوار فقط.
      const items = Array.from(dialog.querySelectorAll<HTMLElement>(FOCUSABLE)).filter(
        (el) => el.offsetParent !== null,
      )
      if (items.length === 0) return
      const firstEl = items[0]
      const lastEl = items[items.length - 1]
      const active = document.activeElement
      if (e.shiftKey && active === firstEl) {
        e.preventDefault()
        lastEl.focus()
      } else if (!e.shiftKey && active === lastEl) {
        e.preventDefault()
        firstEl.focus()
      }
    }

    document.addEventListener('keydown', onKey)
    return () => {
      document.removeEventListener('keydown', onKey)
      document.body.style.overflow = prevOverflow
      previouslyFocused?.focus?.() // إعادة التركيز للعنصر الذي فتح الحوار
    }
  }, [onClose])

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4"
      onMouseDown={onClose}
      role="presentation"
    >
      <div
        ref={dialogRef}
        className="lp-reveal flex max-h-[90dvh] w-full max-w-md flex-col rounded-2xl border border-slate-200 bg-white shadow-xl"
        onMouseDown={(e) => e.stopPropagation()}
        role="dialog"
        aria-modal="true"
        aria-label={title}
        tabIndex={-1}
      >
        {/* العنوان ثابت؛ جسم الحوار يتمرّر عمودياً على الشاشات القصيرة (جوّال) فتبقى
            أزرار الحفظ بالأسفل قابلة للوصول دائماً. */}
        <h2 className="shrink-0 px-5 pb-4 pt-5 text-lg font-semibold text-brand-800">{title}</h2>
        <div className="overflow-y-auto px-5 pb-5">{children}</div>
      </div>
    </div>
  )
}
