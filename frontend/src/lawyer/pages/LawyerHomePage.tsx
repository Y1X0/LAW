import { useAuth } from '@/core/auth/useAuth'
import { Card } from '@/core/ui/primitives'
import { PageHeader } from '@/core/ui/section'
import { Logo } from '@/core/layout/Brand'

/**
 * رئيسية المحامي — أساس LP-1 فقط (ترحيب + تأكيد جاهزية البوابة).
 * لوحة البيانات الفعلية (قضاياي/المهام/أقرب جلسة/آخر الأحداث) تأتي في LP-2 عبر
 * `GET /api/me/legal-summary`. لا نعرض بيانات هنا عمداً حتى يبقى الأساس نظيفاً.
 */
export function LawyerHomePage() {
  const { user } = useAuth()

  return (
    <div className="space-y-5">
      <PageHeader title="الرئيسية" subtitle="بوابة المحامي" />

      <Card className="flex items-center gap-4">
        <Logo className="h-12 w-12 shrink-0" />
        <div>
          <p className="text-base font-semibold text-brand-700">
            {user ? `أهلاً بك، ${user.name}` : 'أهلاً بك'}
          </p>
          <p className="mt-1 text-sm text-slate-600">
            تم تجهيز بوابة المحامي. تظهر لوحتك التفصيلية — قضاياك المفتوحة والمهام المعلّقة
            وأقرب جلسة وآخر الأحداث — في المرحلة التالية.
          </p>
        </div>
      </Card>
    </div>
  )
}
