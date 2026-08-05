import { useState, type FormEvent } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { ApiError } from '@/core/api/types'
import { authApi } from '@/core/api/auth'

/**
 * إعادة تعيين كلمة المرور (B2) — يقرأ token وemail من رابط البريد ويستدعي /auth/reset-password.
 * عند النجاح يوجّه إلى تسجيل الدخول (الخادم يُبطل كل الجلسات عند التغيير). رابط غير صالح ⇒ رسالة.
 */
export function ResetPasswordPage() {
  const [params] = useSearchParams()
  const navigate = useNavigate()
  const token = params.get('token') ?? ''
  const email = params.get('email') ?? ''

  const [password, setPassword] = useState('')
  const [confirm, setConfirm] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  const inputClass =
    'w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20'

  const invalidLink = !token || !email

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setError(null)
    if (password !== confirm) {
      setError('كلمتا المرور غير متطابقتين.')
      return
    }
    setSubmitting(true)
    try {
      await authApi.resetPassword({ token, email, password, password_confirmation: confirm })
      navigate('/login', { replace: true, state: { reset: true } })
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'تعذّر تعيين كلمة المرور. قد يكون الرابط منتهياً.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-50 p-6" dir="rtl">
      <div className="w-full max-w-md rounded-2xl border border-slate-200/80 bg-white p-7 shadow-card">
        <h1 className="text-2xl font-extrabold text-brand-800">إعادة تعيين كلمة المرور</h1>

        {invalidLink ? (
          <div className="mt-6 space-y-4">
            <p className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">
              الرابط غير صالح أو ناقص. اطلب رابطاً جديداً من صفحة «نسيت كلمة المرور».
            </p>
            <Link to="/forgot-password" className="block text-center text-sm font-medium text-brand-600 hover:text-brand-700">
              طلب رابط جديد
            </Link>
          </div>
        ) : (
          <>
            <p className="mt-1 text-sm text-slate-500">لحساب: <b className="text-slate-700">{email}</b></p>
            <form onSubmit={onSubmit} className="mt-6 space-y-4">
              <div className="space-y-1.5">
                <label htmlFor="rp-password" className="block text-sm font-medium text-slate-700">كلمة المرور الجديدة</label>
                <input
                  id="rp-password"
                  type="password"
                  autoComplete="new-password"
                  className={inputClass}
                  placeholder="٨ أحرف على الأقل"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  minLength={8}
                  required
                />
              </div>
              <div className="space-y-1.5">
                <label htmlFor="rp-confirm" className="block text-sm font-medium text-slate-700">تأكيد كلمة المرور</label>
                <input
                  id="rp-confirm"
                  type="password"
                  autoComplete="new-password"
                  className={inputClass}
                  placeholder="أعد إدخال كلمة المرور"
                  value={confirm}
                  onChange={(e) => setConfirm(e.target.value)}
                  minLength={8}
                  required
                />
              </div>

              {error ? <p role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p> : null}

              <button
                type="submit"
                disabled={submitting}
                className="lp-press w-full rounded-lg bg-gold-500 px-4 py-2.5 text-sm font-bold text-brand-800 shadow-sm transition hover:bg-gold-600 hover:text-white disabled:cursor-not-allowed disabled:opacity-60"
              >
                {submitting ? 'جارٍ الحفظ…' : 'تعيين كلمة المرور'}
              </button>
            </form>
          </>
        )}
      </div>
    </div>
  )
}
