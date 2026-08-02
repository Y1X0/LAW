import { useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
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
  const [hint, setHint] = useState(false)
  const [submitting, setSubmitting] = useState(false)

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setError(null)
    setSubmitting(true)
    try {
      await login(email, password)
      navigate('/', { replace: true })
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'تعذّر تسجيل الدخول. حاول مجدداً.')
    } finally {
      setSubmitting(false)
    }
  }

  const inputClass =
    'w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20'

  return (
    <div className="flex min-h-screen flex-col md:flex-row" dir="rtl">
      {/* لوحة النموذج (يمين في RTL) */}
      <div className="flex flex-1 items-center justify-center bg-slate-50 p-6">
        <div className="w-full max-w-md">
          {/* هوية مصغّرة للجوّال */}
          <div className="mb-6 flex flex-col items-center gap-2 md:hidden">
            <ScalesLogo className="h-12 w-12 text-gold-400" />
            <h2 className="text-lg font-extrabold text-brand-800">مكتب العدالة للمحاماة</h2>
          </div>

          <div className="rounded-2xl border border-slate-200/80 bg-white p-7 shadow-card">
            <h1 className="text-2xl font-extrabold text-brand-800">تسجيل الدخول</h1>
            <p className="mt-1 text-sm text-slate-500">يرجى إدخال بياناتك للوصول إلى النظام</p>

            <form onSubmit={onSubmit} className="mt-6 space-y-4">
              <div className="space-y-1.5">
                <label htmlFor="login-email" className="block text-sm font-medium text-slate-700">
                  البريد الإلكتروني
                </label>
                <input
                  id="login-email"
                  type="email"
                  autoComplete="email"
                  className={inputClass}
                  placeholder="أدخل بريدك الإلكتروني"
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
                    placeholder="أدخل كلمة المرور"
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

              <div className="flex items-center justify-between text-sm">
                <label className="flex items-center gap-2 text-slate-600">
                  <input type="checkbox" className="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500/30" />
                  تذكّرني
                </label>
                <button type="button" onClick={() => setHint((h) => !h)} className="font-medium text-brand-600 hover:text-brand-700">
                  نسيت كلمة المرور؟
                </button>
              </div>

              {hint ? (
                <p className="rounded-lg bg-brand-50 px-3 py-2 text-xs text-brand-700">
                  لإعادة تعيين كلمة المرور، تواصل مع إدارة النظام.
                </p>
              ) : null}

              {error ? (
                <p role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">
                  {error}
                </p>
              ) : null}

              <button
                type="submit"
                disabled={submitting}
                className="lp-press w-full rounded-lg bg-gold-500 px-4 py-2.5 text-sm font-bold text-brand-800 shadow-sm transition hover:bg-gold-600 hover:text-white disabled:cursor-not-allowed disabled:opacity-60"
              >
                {submitting ? 'جارٍ الدخول…' : 'تسجيل الدخول'}
              </button>
            </form>

            <p className="mt-5 text-center text-xs text-slate-400">
              تنبيه: إنشاء الحسابات يتم فقط من قبل الإدارة
            </p>
          </div>
        </div>
      </div>

      {/* لوحة الهوية (يسار في RTL) — سطح المكتب فقط */}
      <div className="relative hidden w-[42%] flex-col items-center justify-center overflow-hidden bg-gradient-to-b from-brand-800 to-brand-600 p-10 text-white md:flex">
        {/* زخرفة خفيفة */}
        <div
          className="pointer-events-none absolute inset-0 opacity-[0.06]"
          style={{ backgroundImage: 'radial-gradient(circle at 20% 20%, #fff 1px, transparent 1px)', backgroundSize: '22px 22px' }}
          aria-hidden="true"
        />
        <div className="relative flex flex-col items-center text-center">
          <ScalesLogo className="h-20 w-20 text-gold-400" />
          <h2 className="mt-5 text-3xl font-extrabold tracking-tight">مكتب العدالة للمحاماة</h2>
          <p className="mt-2 text-brand-100">نظام إدارة المكتب</p>
          <div className="mt-10 border-t border-white/15 pt-6">
            <p className="text-lg font-semibold text-gold-400">”العدل أساس الملك“</p>
          </div>
        </div>
      </div>
    </div>
  )
}
