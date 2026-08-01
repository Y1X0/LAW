import { Link } from 'react-router-dom'
import { PageHeader } from '@/core/ui/section'
import { Card } from '@/core/ui/primitives'

/**
 * لوحة المسؤول (ADMIN-1/2: صفحة بداية موجزة).
 * تُستبدَل بلوحة النظام الحقيقية (إحصاءات/نشاط) في ADMIN-4.
 */
export function AdminHomePage() {
  return (
    <div className="space-y-5">
      <PageHeader title="وحدة التحكّم" subtitle="إدارة المنصة" />
      <Card className="lp-reveal space-y-3 text-sm text-slate-600">
        <p>مرحباً بك في وحدة تحكّم المنصّة. لوحة النظام والإحصاءات قيد الإنشاء.</p>
        <p>
          إدارة الحسابات متاحة الآن من{' '}
          <Link to="/admin/users" className="font-medium text-brand-700 hover:underline">
            المستخدمون
          </Link>
          .
        </p>
      </Card>
    </div>
  )
}
