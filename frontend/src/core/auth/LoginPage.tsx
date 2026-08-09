import { useRef, useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { ApiError } from '@/core/api/types'
import { useAuth } from '@/core/auth/useAuth'

/** شعار ميزان العدالة (SVG داخلي، ذهبي). */
function ScalesLogo({ className = 'h-16 w-16' }: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" className={className} fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d="M12 3v18M7 21h10M5 7h14M12 5l-7 2 3 6a3 3 0 0 1-6 0l3-6M12 5l7 2-3 6a3 3 0 0 0 6 0l-3-6" />
    </svg>
  )
}

/**
 * شاشة تسجيل الدخول — لوحة هوية كحلية + نموذج أنيق. نفس منطق الدخول
 * (‏/api/auth/login عبر طبقة المصادقة) — تغيير شكل فقط.
 */
export function LoginPage() {
  const { login } = useAuth()
  const navigate = useNavigate()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [showPassword, setShowPassword] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const [success, setSuccess] = useState(false)
  // حركة العنصر المشترك (FLIP): نفس شعار الميزان يسافر من مكانه في الكارد إلى مركز
  // الشاشة ويكبر — بلا «قطع» ولا شعار بديل. نقيس موضعه الأصلي ثم ننقله.
  const badgeRef = useRef<HTMLDivElement>(null)
  const [flip, setFlip] = useState<{ top: number; left: number; width: number; height: number } | null>(null)
  const [centered, setCentered] = useState(false)

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setError(null)
    setSubmitting(true)
    try {
      await login(email, password)
      // يُحترم تقليل الحركة: انتقال فوري بلا حركة. لا مساس بمنطق الدخول/الـAPI.
      if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        navigate('/', { replace: true })
        return
      }
      // 1) نلتقط موضع الشعار الحالي (نفس العنصر) قبل تحريكه — أساس FLIP بلا قفزة.
      const r = badgeRef.current?.getBoundingClientRect()
      if (r) setFlip({ top: r.top, left: r.left, width: r.width, height: r.height })
      setSuccess(true)
      // 2) بعد رسم النسخة الثابتة في مكانها تماماً، نبدّلها إلى المركز فتنساب بنعومة.
      requestAnimationFrame(() => requestAnimationFrame(() => setCentered(true)))
      // 3) بعد أن يتوسّط الشعار ويثبت متوهّجاً، نفتح اللوحة.
      window.setTimeout(() => navigate('/', { replace: true }), 1150)
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'تعذّر تسجيل الدخول. حاول مجدداً.')
      setSubmitting(false)
    }
  }

  const inputClass =
    'w-full rounded-md border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-brand-800 focus:ring-2 focus:ring-slate-900/10'

  return (
    // خلفية فحمي عميق + كارد وسطية. عند الإرسال تظهر طبقة الانتقال «الصامت الفخم».
    <div dir="rtl" className="flex min-h-screen items-center justify-center bg-[#111318] px-4 py-8">
      <div className={`lp-reveal w-full max-w-md rounded-xl border border-slate-200 bg-white p-8 shadow-[0_10px_40px_-15px_rgba(0,0,0,0.6)] transition-opacity duration-500 ease-in-out ${success ? 'opacity-0' : 'opacity-100'}`}>
        {/* شعار الميزان داخل دائرة بحدّ ذهبي مطفأ — لمسة كلاسيكية نظيفة.
            عند الدخول يُخفى الأصل وتحلّ محلّه نسخة ثابتة تنساب للمركز (نفس الشعار). */}
        <div className="mb-6 flex flex-col items-center text-center">
          <div
            ref={badgeRef}
            className="mb-3 flex h-14 w-14 items-center justify-center rounded-full border border-gold-400/40 bg-[#111318]"
            style={{ visibility: success ? 'hidden' : 'visible' }}
          >
            <ScalesLogo className="h-7 w-7 text-gold-400" />
          </div>
          <h1 className="text-xl font-bold tracking-tight text-brand-800">مكتب العدالة للمحاماة</h1>
          <p className="mt-1 text-xs text-slate-500">المنصّة المؤسسية الآمنة</p>
        </div>

        <form onSubmit={onSubmit} className="space-y-4">
          <div className="space-y-1.5">
            <label htmlFor="login-email" className="block text-sm font-medium text-slate-700">
              البريد الإلكتروني
            </label>
            <input
              id="login-email"
              type="email"
              autoComplete="email"
              className={inputClass}
              placeholder="name@lawfirm.com"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
            />
          </div>

          <div className="space-y-1.5">
            <label htmlFor="login-password" className="block text-sm font-medium text-slate-700">
              كلمة المرور
            </label>
            <div className="relative">
              <input
                id="login-password"
                type={showPassword ? 'text' : 'password'}
                autoComplete="current-password"
                className={`${inputClass} pl-11`}
                placeholder="••••••••"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
              />
              <button
                type="button"
                onClick={() => setShowPassword((s) => !s)}
                aria-label={showPassword ? 'إخفاء كلمة السر' : 'إظهار كلمة السر'}
                className="absolute inset-y-0 left-0 flex items-center px-3 text-slate-400 hover:text-slate-600"
              >
                <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                  {showPassword ? (
                    <path d="M3 3l18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 4.2A9.8 9.8 0 0 1 12 4c5 0 9 4.5 9 8a12 12 0 0 1-2.2 3.2M6.1 6.1A12.6 12.6 0 0 0 3 12c0 3.5 4 8 9 8 1.4 0 2.7-.3 3.9-.9" />
                  ) : (
                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7zM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z" />
                  )}
                </svg>
              </button>
            </div>
          </div>

          <div className="flex justify-start text-sm">
            <Link to="/forgot-password" className="font-medium text-slate-500 hover:text-brand-800">
              نسيت كلمة المرور؟
            </Link>
          </div>

          {error ? (
            <p role="alert" className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
              {error}
            </p>
          ) : null}

          {/* زر فحمي داكن بحدّ ذهبي مطفأ */}
          <button
            type="submit"
            disabled={submitting}
            className="lp-press flex w-full items-center justify-center rounded-md border border-gold-400/60 bg-[#111318] px-4 py-2.5 text-sm font-bold text-white transition hover:bg-black disabled:cursor-not-allowed disabled:opacity-60"
          >
            {submitting ? 'جارٍ الدخول…' : 'تسجيل الدخول'}
          </button>
        </form>

        <p className="mt-6 text-center text-xs text-slate-400">
          تنبيه: إنشاء الحسابات يتم فقط من قبل الإدارة
        </p>
      </div>

      {/* حركة العنصر المشترك: طبقة فحمي تظهر بنعومة خلف الشعار، ونسخة ثابتة من نفس
          شعار الميزان تنطلق من موضعه في الكارد إلى مركز الشاشة وتكبر وتثبت متوهّجة
          (لا تختفي ولا قطع) ثم تُفتح اللوحة. */}
      {success && (
        <div
          aria-hidden="true"
          className={`fixed inset-0 z-40 bg-[#111318] transition-opacity duration-700 ease-in-out ${centered ? 'opacity-100' : 'opacity-0'}`}
        />
      )}
      {success && flip && (
        <div
          role="status"
          aria-live="polite"
          className="fixed z-50 flex items-center justify-center rounded-full border border-gold-400/40 bg-[#111318]"
          style={
            centered
              ? {
                  top: '50%',
                  left: '50%',
                  width: 132,
                  height: 132,
                  transform: 'translate(-50%, -50%)',
                  transition: 'top 800ms cubic-bezier(.16,1,.3,1), left 800ms cubic-bezier(.16,1,.3,1), width 800ms cubic-bezier(.16,1,.3,1), height 800ms cubic-bezier(.16,1,.3,1), filter 800ms ease',
                  filter: 'drop-shadow(0 0 30px rgba(197,160,89,0.8))',
                }
              : { top: flip.top, left: flip.left, width: flip.width, height: flip.height }
          }
        >
          <span className="sr-only">جارٍ الدخول…</span>
          <ScalesLogo className="h-1/2 w-1/2 text-gold-400" />
        </div>
      )}
    </div>
  )
}
