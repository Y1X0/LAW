/**
 * شعار المكتب (SVG داخلي — لا جلب أصول، حادّ على كل الكثافات).
 * ميزان العدالة بالكحلي مع لمسة ذهبية = هوية المكتب.
 */
export function Logo({ className = 'h-9 w-9' }: { className?: string }) {
  return (
    <svg viewBox="0 0 48 48" className={className} role="img" aria-label="شعار المكتب">
      {/* العمود والقاعدة */}
      <path
        d="M24 7v30M15 40h18M24 40v-3"
        stroke="currentColor"
        strokeWidth="2.4"
        strokeLinecap="round"
        className="text-brand-700"
        fill="none"
      />
      {/* العارضة وخيوط الكفّتين */}
      <path
        d="M11 13h26M24 7l0 0M13 13l-4 9M35 13l4 9M13 13l4 9"
        stroke="currentColor"
        strokeWidth="2.2"
        strokeLinecap="round"
        strokeLinejoin="round"
        className="text-brand-600"
        fill="none"
      />
      {/* الكفّتان (ذهبي) */}
      <path
        d="M4 22a5 5 0 0 0 10 0zM34 22a5 5 0 0 0 10 0z"
        className="fill-gold-400"
      />
      {/* عقدة التعليق العلوية (ذهبي) */}
      <circle cx="24" cy="7" r="2.6" className="fill-gold-400" />
    </svg>
  )
}

/** شعار + اسم المكتب (يُستخدم في رأس الشريط الجانبي). */
export function Brand({ subtitle }: { subtitle?: string }) {
  return (
    <div className="flex items-center gap-2.5">
      <Logo className="h-9 w-9 shrink-0" />
      <div className="leading-tight">
        <div className="text-base font-bold text-brand-700">مكتب المحاماة</div>
        {subtitle ? <div className="text-[11px] font-medium text-gold-600">{subtitle}</div> : null}
      </div>
    </div>
  )
}
