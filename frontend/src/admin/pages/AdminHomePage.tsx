import { PageHeader } from '@/core/ui/section'
import { Card } from '@/core/ui/primitives'

/**
 * لوحة المسؤول (ADMIN-1: عنصر نائب مؤقّت).
 * تُستبدَل بلوحة النظام الحقيقية (إحصاءات/نشاط) في ADMIN-4.
 */
export function AdminHomePage() {
  return (
    <div className="space-y-5">
      <PageHeader title="وحدة التحكّم" subtitle="إدارة المنصة" />
      <Card className="lp-reveal text-sm text-slate-500">
        مرحباً بك في وحدة تحكّم المنصة. لوحة النظام والإدارة قيد الإنشاء.
      </Card>
    </div>
  )
}
