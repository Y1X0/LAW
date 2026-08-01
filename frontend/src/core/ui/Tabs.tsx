import { useLayoutEffect, useRef, useState } from 'react'

/**
 * تبويبات بمؤشّر ذهبي منزلق — يُحسب موضعه من أبعاد الزر النشِط (RTL-آمن).
 * لا يغيّر السلوك: مجرّد طبقة عرض فوق أزرار role="tab". الحركة على
 * transform/width وتُعطَّل تلقائياً مع prefers-reduced-motion (عبر CSS).
 */
export function Tabs<K extends string>({
  tabs,
  active,
  onChange,
}: {
  tabs: { key: K; label: string }[]
  active: K
  onChange: (key: K) => void
}) {
  const listRef = useRef<HTMLDivElement>(null)
  const [indicator, setIndicator] = useState<{ left: number; width: number }>({ left: 0, width: 0 })

  useLayoutEffect(() => {
    const list = listRef.current
    if (!list) return
    const el = list.querySelector<HTMLButtonElement>(`[data-tab-key="${active}"]`)
    if (el) setIndicator({ left: el.offsetLeft, width: el.offsetWidth })
  }, [active, tabs])

  return (
    <div className="overflow-x-auto border-b border-slate-200">
      <div ref={listRef} role="tablist" className="relative flex gap-1">
        {tabs.map((t) => {
          const isActive = active === t.key
          return (
            <button
              key={t.key}
              data-tab-key={t.key}
              role="tab"
              aria-selected={isActive}
              onClick={() => onChange(t.key)}
              className={`shrink-0 px-4 py-2 text-sm transition-colors ${
                isActive ? 'font-semibold text-brand-700' : 'text-slate-500 hover:text-brand-700'
              }`}
            >
              {t.label}
            </button>
          )
        })}
        <span
          aria-hidden="true"
          className="lp-tab-indicator"
          style={{ transform: `translateX(${indicator.left}px)`, width: indicator.width }}
        />
      </div>
    </div>
  )
}
