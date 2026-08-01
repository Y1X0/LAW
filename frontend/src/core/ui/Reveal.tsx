import { useEffect, useRef, useState, type ReactNode } from 'react'

/**
 * ظهور تدريجي (fade + slide-up) عند دخول العنصر إطار الرؤية — عبر IntersectionObserver.
 * الحركة على transform/opacity فقط، وتُعطَّل تلقائياً مع prefers-reduced-motion (عبر CSS).
 */
export function Reveal({
  children,
  delay = 0,
  className = '',
}: {
  children: ReactNode
  delay?: number
  className?: string
}) {
  const ref = useRef<HTMLDivElement>(null)
  const [shown, setShown] = useState(false)

  useEffect(() => {
    const el = ref.current
    if (!el) return
    const io = new IntersectionObserver(
      (entries) => {
        for (const e of entries) {
          if (e.isIntersecting) {
            setShown(true)
            io.disconnect()
          }
        }
      },
      { threshold: 0.08 },
    )
    io.observe(el)
    return () => io.disconnect()
  }, [])

  return (
    <div
      ref={ref}
      className={`${shown ? 'lp-reveal' : 'opacity-0'} ${className}`}
      style={delay && shown ? { animationDelay: `${delay}ms` } : undefined}
    >
      {children}
    </div>
  )
}
