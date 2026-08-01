import { PageHeader } from '@/core/ui/section'
import { Card } from '@/core/ui/primitives'

/**
 * لوحة الموارد البشرية (HR-1: عنصر نائب مؤقّت).
 * تُستبدَل ببطاقات KPI الحقيقية في HR-2 (عدد الموظفين · إجازات معلّقة · غياب اليوم).
 */
export function HrHomePage() {
  return (
    <div className="space-y-5">
      <PageHeader title="الموارد البشرية" subtitle="لوحة إدارة الموظفين" />
      <Card className="lp-reveal text-sm text-slate-500">
        مرحباً بك في بوابة الموارد البشرية. لوحة التحكّم قيد الإنشاء.
      </Card>
    </div>
  )
}
