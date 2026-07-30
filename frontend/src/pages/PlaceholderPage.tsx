import { Card } from '../components/ui/primitives'

/** صفحة بديلة للأقسام قيد الإنشاء (#59–#62). */
export function PlaceholderPage({ title }: { title: string }) {
  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold">{title}</h1>
      <Card>
        <p className="text-sm text-slate-500">هذه الصفحة قيد الإنشاء ضمن الـ Epic (الشاشات #59–#62).</p>
      </Card>
    </div>
  )
}
