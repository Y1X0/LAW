import { useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { ApiError } from '../api/types'
import { useAuth } from '../auth/useAuth'
import { Button, Card, Field } from '../components/ui/primitives'

/** شاشة تسجيل الدخول — تستدعي /api/auth/login عبر طبقة المصادقة. */
export function LoginPage() {
  const { login } = useAuth()
  const navigate = useNavigate()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<string | null>(null)
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

  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-50 p-4">
      <Card className="w-full max-w-sm">
        <h1 className="mb-1 text-xl font-bold text-slate-900">تسجيل الدخول</h1>
        <p className="mb-5 text-sm text-slate-500">بوابة الموظف</p>
        <form onSubmit={onSubmit} className="space-y-4">
          <Field
            label="البريد الإلكتروني"
            type="email"
            autoComplete="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
          />
          <Field
            label="كلمة المرور"
            type="password"
            autoComplete="current-password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
          />
          {error ? (
            <p role="alert" className="text-sm text-red-600">
              {error}
            </p>
          ) : null}
          <Button type="submit" className="w-full" disabled={submitting}>
            {submitting ? 'جارٍ الدخول…' : 'دخول'}
          </Button>
        </form>
      </Card>
    </div>
  )
}
