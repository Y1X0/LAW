import { Card } from '@/core/ui/primitives'
import { PageHeader } from '@/core/ui/section'

/**
 * حاجب أساس لمسارات المحامي التي تُبنى شاشاتها لاحقاً (LP-2..LP-5).
 * وجوده يجعل التنقّل والحماية قابلين للاختبار الآن دون بناء أي شاشة بيانات.
 */
export function ComingSoon({ title, phase }: { title: string; phase: string }) {
  return (
    <div className="space-y-5">
      <PageHeader title={title} subtitle="بوابة المحامي" />
      <Card className="text-center text-sm text-slate-500">
        <p>هذه الشاشة قيد الإنشاء وتتوفّر في {phase}.</p>
      </Card>
    </div>
  )
}
