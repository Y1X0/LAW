/**
 * شعار المكتب (SVG داخلي — لا جلب أصول، حادّ على كل الكثافات).
 * ميزان العدالة بالكحلي مع لمسة ذهبية = هوية المكتب.
 * tone='light' لخلفية داكنة (الشريط الجانبي الكحلي).
 */
export function Logo({
  className = 'h-9 w-9',
  tone = 'brand',
}: {
  className?: string
  tone?: 'brand' | 'light'
}) {
  const s1 = tone === 'light' ? 'text-white' : 'text-brand-700'
  const s2 = tone === 'light' ? 'text-gold-400' : 'text-brand-600'
  return (
    <svg viewBox="0 0 48 48" className={className} role="img" aria-label="شعار المكتب">
      <path
        d="M24 7v30M15 40h18M24 40v-3"
        stroke="currentColor"
        strokeWidth="2.4"
        strokeLinecap="round"
        className={s1}
        fill="none"
      />
      <path
        d="M11 13h26M24 7l0 0M13 13l-4 9M35 13l4 9M13 13l4 9"
        stroke="currentColor"
        strokeWidth="2.2"
        strokeLinecap="round"
        strokeLinejoin="round"
        className={s2}
        fill="none"
      />
      <path d="M4 22a5 5 0 0 0 10 0zM34 22a5 5 0 0 0 10 0z" className="fill-gold-400" />
      <circle cx="24" cy="7" r="2.6" className="fill-gold-400" />
    </svg>
  )
}

/** شعار + اسم المكتب. onDark=true لرأس الشريط الجانبي الكحلي. */
export function Brand({ subtitle, onDark = false }: { subtitle?: string; onDark?: boolean }) {
  return (
    <div className="flex items-center gap-2.5">
      <Logo className="h-9 w-9 shrink-0" tone={onDark ? 'light' : 'brand'} />
      <div className="leading-tight">
        <div className={`text-base font-bold ${onDark ? 'text-white' : 'text-brand-700'}`}>
          مكتب المحاماة
        </div>
        {subtitle ? (
          <div className={`text-[11px] font-medium ${onDark ? 'text-gold-400' : 'text-gold-600'}`}>
            {subtitle}
          </div>
        ) : null}
      </div>
    </div>
  )
}
