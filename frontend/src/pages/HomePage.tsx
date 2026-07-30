import { useAuth } from '../auth/useAuth'
import { Card } from '../components/ui/primitives'

/** صفحة الهبوط بعد الدخول (الأساس #57) — الشاشات التفصيلية تُبنى في #58–#62. */
export function HomePage() {
  const { user } = useAuth()
  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold">مرحباً{user ? `، ${user.name}` : ''}</h1>
      <Card>
        <p className="text-sm text-slate-600">
          تم تسجيل دخولك بنجاح. استخدم القائمة الجانبية للتنقّل بين لوحتك وراتبك وحضورك وإجازاتك وملفك.
        </p>
      </Card>
    </div>
  )
}

/** صفحة بديلة للأقسام قيد الإنشاء (#58–#62). */
export function PlaceholderPage({ title }: { title: string }) {
  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold">{title}</h1>
      <Card>
        <p className="text-sm text-slate-500">هذه الصفحة قيد الإنشاء ضمن الـ Epic (الشاشات #58–#62).</p>
      </Card>
    </div>
  )
}
