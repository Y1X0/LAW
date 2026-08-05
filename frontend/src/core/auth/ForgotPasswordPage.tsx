import { useState, type FormEvent } from 'react'
import { Link } from 'react-router-dom'
import { ApiError } from '@/core/api/types'
import { authApi } from '@/core/api/auth'

/**
 * نسيت كلمة المرور (B2) — يطلب البريد ويستدعي /auth/forgot-password. يعرض رسالة عامّة دائماً
 * (منع تعداد الحسابات، اتّساقاً مع الخادم). الرابط الفعلي يصل عبر البريد إلى /reset-password.
 */
export function ForgotPasswordPage() {
  const [email, setEmail] = useState('')
  const [sent, setSent] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  const inputClass =
    'w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20'

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setError(null)
    setSubmitting(true)
    try {
      await authApi.forgotPassword(email)
      setSent(true)
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'تعذّر إرسال الطلب. حاول مجدداً.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-50 p-6" dir="rtl">
      <div className="w-full max-w-md rounded-2xl border border-slate-200/80 bg-white p-7 shadow-card">
        <h1 className="text-2xl font-extrabold text-brand-800">نسيت كلمة المرور</h1>
        <p className="mt-1 text-sm text-slate-500">أدخل بريدك الإلكتروني وسنرسل لك رابط إعادة التعيين.</p>

        {sent ? (
          <div className="mt-6 space-y-4">
            <p className="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
              إن كان البريد مسجّلاً فسيصلك رابط إعادة التعيين. تحقّق من بريدك.
            </p>
            <Link to="/login" className="block text-center text-sm font-medium text-brand-600 hover:text-brand-700">
              العودة إلى تسجيل الدخول
            </Link>
          </div>
        ) : (
          <form onSubmit={onSubmit} className="mt-6 space-y-4">
            <div className="space-y-1.5">
              <label htmlFor="fp-email" className="block text-sm font-medium text-slate-700">البريد الإلكتروني</label>
              <input
                id="fp-email"
                type="email"
                autoComplete="email"
                className={inputClass}
                placeholder="أدخل بريدك الإلكتروني"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
              />
            </div>

            {error ? <p role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p> : null}

            <button
              type="submit"
              disabled={submitting}
              className="lp-press w-full rounded-lg bg-gold-500 px-4 py-2.5 text-sm font-bold text-brand-800 shadow-sm transition hover:bg-gold-600 hover:text-white disabled:cursor-not-allowed disabled:opacity-60"
            >
              {submitting ? 'جارٍ الإرسال…' : 'إرسال رابط إعادة التعيين'}
            </button>

            <Link to="/login" className="block text-center text-sm font-medium text-brand-600 hover:text-brand-700">
              العودة إلى تسجيل الدخول
            </Link>
          </form>
        )}
      </div>
    </div>
  )
}
